<?php

namespace YdbPlatform\Ydb\Sessions;

use YdbPlatform\Ydb\Retry\Retry;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Contracts\SessionPoolContract;

class FileSessionPool implements SessionPoolContract
{
    /**
     * @var array
     */
    protected static $sessions = [];

    /**
     * @var string
     */
    protected static $table;

    /**
     * @var string
     */
    protected static $filepath;
    /**
     * @var Retry
     */
    protected static $retry;

    /**
     * @var resource|null
     */
    private static $lockHandle;

    /**
     * @var int
     */
    private static $lockDepth = 0;

    /**
     * @param array $config
     */
    public function __construct(array $config, Retry &$retry)
    {
        static::$table = $config['table'];
        static::$filepath = $config['filepath'];
        static::$retry = $retry;

        $this->withLock(function () {});
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->withLock(function () {
            $this->save();
        });
    }

    /**
     * Holds an exclusive lock on the pool file for $fn's whole duration; reloads once, on the outermost call only.
     *
     * @param callable $fn
     * @return mixed
     */
    private function withLock(callable $fn)
    {
        $isOutermost = (static::$lockDepth++ === 0);

        if ($isOutermost)
        {
            static::$lockHandle = fopen(static::$filepath . '.lock', 'c');
            flock(static::$lockHandle, LOCK_EX);
            $this->load();
        }

        try
        {
            return $fn();
        }
        finally
        {
            if (--static::$lockDepth === 0)
            {
                flock(static::$lockHandle, LOCK_UN);
                fclose(static::$lockHandle);
                static::$lockHandle = null;
            }
        }
    }

    /**
     * @return void
     */
    protected function load()
    {
        if (is_file(static::$filepath))
        {
            $contents = file_get_contents(static::$filepath);
            $sessions = json_decode($contents);

            $fresh = [];
            foreach ($sessions ?? [] as $session_info)
            {
                $session = new Session(static::$table, $session_info->id);
                if ($session_info->taken)
                {
                    // markTaken(), not take() - avoids a stale-snapshot save() mid-load(), see Session::markTaken().
                    $session->markTaken();
                }
                $fresh[$session->id()] = $session;
            }

            static::$sessions = $fresh;
        }
    }

    /**
     * @return void
     */
    protected function save()
    {
        $sessions = [];
        foreach (static::$sessions as $session_id => $session)
        {
            $sessions[] = (object)[
                'id' => $session->id(),
                'taken' => $session->isBusy(),
            ];
        }

        // Write-then-rename, same as Iam::saveToken(), avoids a torn read mid-write.
        $tmpPath = static::$filepath . '.tmp' . bin2hex(random_bytes(10));
        file_put_contents($tmpPath, json_encode($sessions));
        rename($tmpPath, static::$filepath);
    }

    /**
     * @return Session|null
     */
    public function getIdleSession()
    {
        return $this->withLock(function () {
            foreach (static::$sessions as $session_id => $session)
            {
                if ($session->isIdle())
                {
                    $this->syncSession($session_id);
                    // Persist taken before releasing the lock, or a concurrent process could take it too.
                    $session->take();
                    return $session;
                }
            }

            return null;
        });
    }

    /**
     * @param Session $session
     * @return void
     */
    public function addSession(Session $session)
    {
        $this->withLock(function () use ($session) {
            static::$sessions[$session->id()] = $session;

            $this->save();
        });
    }

    /**
     * @param string $session_id
     * @return void
     */
    public function dropSession($session_id)
    {
        $this->withLock(function () use ($session_id) {
            $session = static::$sessions[$session_id] ?? null;

            if ($session)
            {
                if ($session->isAlive())
                {
                    if (static::$retry==null){
                        static::$retry = new Retry();
                    }
                    static::$retry->retry(function () use ($session) {
                        $session->delete();
                    },true);
                }
                else
                {
                    unset(static::$sessions[$session_id]);
                }

                $this->save();
            }
        });
    }

    /**
     * @param string $session_id
     * @return void
     */
    public function syncSession($session_id)
    {
        $this->withLock(function () use ($session_id) {
            $session = static::$sessions[$session_id] ?? null;

            if ($session && $session->id() !== $session_id)
            {
                unset(static::$sessions[$session_id]);
                static::$sessions[$session->id()] = $session;

                $this->save();
            }
        });
    }

    /**
     * @param Session $session
     * @return void
     */
    public function sessionTaken(Session $session)
    {
        $this->withLock(function () {
            $this->save();
        });
    }

    /**
     * @param Session $session
     * @return void
     */
    public function sessionReleased(Session $session)
    {
        $this->withLock(function () {
            $this->save();
        });
    }
}

<?php

namespace YdbPlatform\Ydb\Sessions;

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
     * @param array $config
     */
    public function __construct(array $config)
    {
        static::$table = $config['table'];
        static::$filepath = $config['filepath'];

        $this->load();
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->load();
        $this->save();
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
            foreach ($sessions as $session_info)
            {
                $session = new Session(static::$table, $session_info->id);
                if ($session_info->taken)
                {
                    $session->take();
                }
                static::$sessions[$session->id()] = $session;
            }
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
        file_put_contents(static::$filepath, json_encode($sessions));
    }

    /**
     * @return Session|null
     */
    public function getIdleSession()
    {
        $this->load();

        foreach (static::$sessions as $session_id => $session)
        {
            if ($session->isIdle())
            {
                $this->syncSession($session_id);
                return $session;
            }
        }

        return null;
    }

    /**
     * @param Session $session
     * @return void
     */
    public function addSession(Session $session)
    {
        static::$sessions[$session->id()] = $session;

        $this->save();
    }

    /**
     * @param string $session_id
     * @return void
     */
    public function dropSession($session_id)
    {
        $session = static::$sessions[$session_id] ?? null;

        if ($session)
        {
            if ($session->isAlive())
            {
                $session->delete();
            }
            else
            {
                unset(static::$sessions[$session_id]);
            }

            $this->save();
        }
    }

    /**
     * @param string $session_id
     * @return void
     */
    public function syncSession($session_id)
    {
        $session = static::$sessions[$session_id] ?? null;

        if ($session && $session->id() !== $session_id)
        {
            unset(static::$sessions[$session_id]);
            static::$sessions[$session->id()] = $session;

            $this->save();
        }
    }

    /**
     * @param Session $session
     * @return void
     */
    public function sessionTaken(Session $session)
    {
        $this->save();
    }

    /**
     * @param Session $session
     * @return void
     */
    public function sessionReleased(Session $session)
    {
        $this->save();
    }
}

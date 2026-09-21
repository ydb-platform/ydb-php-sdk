<?php

namespace YdbPlatform\Ydb\Sessions;

use YdbPlatform\Ydb\Retry\Retry;
use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Contracts\SessionPoolCapacityContract;
use YdbPlatform\Ydb\Exceptions\Ydb\ClientResourceExhaustedException;

class MemorySessionPool implements SessionPoolCapacityContract
{
    /**
     * @var array
     */
    protected $sessions = [];
    protected $retry;

    /**
     * @var int|null
     */
    protected $maxSize;

    /**
     * @var int
     */
    protected $reservedSlots = 0;

    public function __construct(Retry &$retry, $maxSize = null)
    {
        $this->retry = $retry;
        $this->setMaxSize($maxSize);
    }

    /**
     * @param int|null $maxSize
     * @return void
     */
    public function setMaxSize($maxSize)
    {
        if (!is_null($maxSize) && (!is_int($maxSize) || $maxSize < 1)) {
            throw new \InvalidArgumentException('Session pool max size must be a positive integer');
        }

        $this->maxSize = $maxSize;
    }

    /**
     * Destroy all current sessions.
     * @return void
     */
    public function __destruct()
    {
        foreach ($this->sessions as $session_id => $session) {
            try {
                $this->retry->retry(function () use ($session) {
                    $session->delete();
                }, true);
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * @return Session|null
     */
    public function getIdleSession()
    {
        foreach ($this->sessions as $session_id => $session)
        {
            if ($session->isIdle())
            {
                $this->syncSession($session_id);
                return $session;
            }
        }
    }

    /**
     * @param Session $session
     * @return void
     */
    public function addSession(Session $session)
    {
        if (!isset($this->sessions[$session->id()])
            && !is_null($this->maxSize)
            && count($this->sessions) >= $this->maxSize
        ) {
            throw new ClientResourceExhaustedException(
                'YDB session pool size limit of ' . $this->maxSize . ' has been reached'
            );
        }

        $this->sessions[$session->id()] = $session;
    }

    /**
     * @param string $session_id
     * @return void
     */
    public function dropSession($session_id)
    {
        $session = $this->sessions[$session_id] ?? null;

        if ($session)
        {
            if ($session->isAlive())
            {
                $session->delete();
            }
            else
            {
                unset($this->sessions[$session_id]);
            }
        }
    }

    /**
     * @param string $session_id
     * @return void
     */
    public function syncSession($session_id)
    {
        $session = $this->sessions[$session_id] ?? null;

        if ($session && $session->id() !== $session_id)
        {
            unset($this->sessions[$session_id]);
            $this->sessions[$session->id()] = $session;
        }
    }

    /**
     * @return void
     * @throws ClientResourceExhaustedException
     */
    public function reserveSessionSlot()
    {
        if (!is_null($this->maxSize)
            && count($this->sessions) + $this->reservedSlots >= $this->maxSize
        ) {
            throw new ClientResourceExhaustedException(
                'YDB session pool size limit of ' . $this->maxSize . ' has been reached'
            );
        }

        $this->reservedSlots++;
    }

    /**
     * @return void
     */
    public function releaseSessionSlot()
    {
        if ($this->reservedSlots > 0) {
            $this->reservedSlots--;
        }
    }

    /**
     * @param Session $session
     * @return void
     */
    public function sessionTaken(Session $session)
    {
        // do nothing
    }

    /**
     * @param Session $session
     * @return void
     */
    public function sessionReleased(Session $session)
    {
        // do nothing
    }
}

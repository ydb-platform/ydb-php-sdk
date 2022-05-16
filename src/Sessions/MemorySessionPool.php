<?php

namespace YdbPlatform\Ydb\Sessions;

use YdbPlatform\Ydb\Session;
use YdbPlatform\Ydb\Contracts\SessionPoolContract;

class MemorySessionPool implements SessionPoolContract
{
    /**
     * @var array
     */
    protected static $sessions = [];

    /**
     * Destroy all current sessions.
     * @return void
     */
    public function __destruct()
    {
        foreach (static::$sessions as $session_id => $session)
        {
            $session->delete();
        }
    }

    /**
     * @return Session|null
     */
    public function getIdleSession()
    {
        foreach (static::$sessions as $session_id => $session)
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
        static::$sessions[$session->id()] = $session;
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

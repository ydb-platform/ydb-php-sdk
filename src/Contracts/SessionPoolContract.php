<?php

namespace YdbPlatform\Ydb\Contracts;

use YdbPlatform\Ydb\Session;

interface SessionPoolContract
{
    /**
     * @return Session|null
     */
    public function getIdleSession();

    /**
     * @param Session $session
     * @return void
     */
    public function addSession(Session $session);

    /**
     * @param string $session_id
     * @return void
     */
    public function dropSession($session_id);

    /**
     * @param string $session_id
     * @return void
     */
    public function syncSession($session_id);

    /**
     * @param Session $session
     * @return void
     */
    public function sessionTaken(Session $session);

    /**
     * @param Session $session
     * @return void
     */
    public function sessionReleased(Session $session);
}

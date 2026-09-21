<?php

namespace YdbPlatform\Ydb\Contracts;

interface SessionPoolCapacityContract extends SessionPoolContract
{
    /**
     * Reserve capacity before creating a server-side session.
     *
     * @return void
     */
    public function reserveSessionSlot();

    /**
     * Release a previously reserved creation slot.
     *
     * @return void
     */
    public function releaseSessionSlot();
}

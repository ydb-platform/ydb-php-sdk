<?php

namespace YdbPlatform\Ydb\Contracts;

interface SessionPoolCapacityContract extends SessionPoolContract
{
    /**
     * Configure the maximum number of sessions managed by the pool.
     *
     * @param int|null $maxSize
     * @return void
     */
    public function setMaxSize($maxSize);

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

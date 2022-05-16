<?php

namespace YdbPlatform\Ydb\Contracts;

interface IamTokenContract
{
    /**
     * @param bool $force
     * @return string|null
     */
    public function token($force = false);
}

<?php

namespace YdbPlatform\Ydb\Slo;

abstract class Command
{
    public $name = "";
    public $description = "";

    public abstract function execute(Config $config);
}

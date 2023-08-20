<?php

namespace YdbPlatform\Ydb\Slo;

abstract class Command
{
    public $name = "";
    public $description = "";
    public $options = [];


    public abstract function execute(string $endpoint, string $path, array $options);

    public function generateOptions(array $args): array
    {
        print_r($args);
        $result = [];
        for ($i = 0; $i < count($args) - 1; $i++) {
            if (substr($args[$i], 0, 1) != "-") continue;
            if (substr($args[$i + 1], 0, 1) == "-") continue;
            $arg = substr($args[$i], 1);
            $option = null;
            foreach ($this->options as $opt) {
                if (in_array($arg, $opt["alias"])) {
                    $option = $opt;
                    break;
                }
            }
            if ($option) {
                $result[$opt["alias"][0]] = $args[$i + 1];
            }
        }
        return $result;
    }
}

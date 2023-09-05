<?php

namespace YdbPlatform\Ydb\Slo;

use YdbPlatform\Ydb\Types\DoubleType;
use YdbPlatform\Ydb\Types\TimestampType;
use YdbPlatform\Ydb\Types\Uint64Type;
use YdbPlatform\Ydb\Types\Utf8Type;

class DataGenerator
{
    public $currentObjectId = 0;
    /**
     * @param int $initialDataCount
     */
    public function __construct(int $initialDataCount)
    {
    }

    public function getMaxId()
    {
        return $this->currentObjectId;
    }

    public function getRandomId()
    {
        return round(lcg_value() * $this->currentObjectId);
    }
    public function getUpsertData()
    {
        $this->currentObjectId++;
        return [
            "\$id" => (new Uint64Type($this->currentObjectId))->toTypedValue(),
            "\$payload_str" => (new Utf8Type($this->generateRandomString()))->toTypedValue(),
            "\$payload_double" => (new DoubleType(lcg_value()))->toTypedValue(),
            "\$payload_timestamp" => (new TimestampType(time()))->toTypedValue()
        ];
    }

    protected function generateRandomString()
    {
        return base64_encode(bin2hex(random_bytes(round(lcg_value() * 20 + 20))));
    }

}

<?php

namespace YdbPlatform\Ydb\Slo;

use YdbPlatform\Ydb\Types\DoubleType;
use YdbPlatform\Ydb\Types\TimestampType;
use YdbPlatform\Ydb\Types\Uint64Type;
use YdbPlatform\Ydb\Types\Utf8Type;

class DataGenerator
{
    /** @var int id of the last generated row */
    public $currentObjectId;

    /**
     * @param int $startId ids are generated starting from $startId + 1, every writer
     *                     needs its own range to avoid overwriting rows of the others
     */
    public function __construct(int $startId)
    {
        $this->currentObjectId = $startId;
    }

    public function getMaxId(): int
    {
        return $this->currentObjectId;
    }

    public function getUpsertData(): array
    {
        $this->currentObjectId++;
        return [
            "\$id" => (new Uint64Type($this->currentObjectId))->toTypedValue(),
            "\$payload_str" => (new Utf8Type($this->generateRandomString()))->toTypedValue(),
            "\$payload_double" => (new DoubleType(lcg_value()))->toTypedValue(),
            "\$payload_timestamp" => (new TimestampType(time()))->toTypedValue()
        ];
    }

    protected function generateRandomString(): string
    {
        return base64_encode(bin2hex(random_bytes((int)round(lcg_value() * 20 + 20))));
    }
}

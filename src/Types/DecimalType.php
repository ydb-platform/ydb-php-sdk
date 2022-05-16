<?php

namespace YdbPlatform\Ydb\Types;

class DecimalType extends AbstractType
{
    /**
     * @var int
     */
    protected $digits = 10;

    /**
     * @var int
     */
    protected $scale = 2;

    /**
     * @param int $digits
     * @return $this
     */
    public function digits($digits)
    {
        $this->digits = $digits;
        return $this;
    }

    /**
     * @param int $scale
     * @return $this
     */
    public function scale($scale)
    {
        $this->scale = $scale;
        return $this;
    }

    /**
     * @inherit
     */
    protected function normalizeValue($value)
    {
        return (float)$value;
    }

    /**
     * @inherit
     */
    protected function getYqlString()
    {
        return 'Decimal(' . $this->quoteString($this->value) . ', ' . $this->digits . ', ' . $this->scale . ')';
    }
}

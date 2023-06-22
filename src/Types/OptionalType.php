<?php

namespace YdbPlatform\Ydb\Types;

use Ydb\Type;

class OptionalType extends AbstractType
{
    /**
     * @var string
     */
    protected $itemType;

    /**
     * @inherit
     */
    protected function normalizeValue($value)
    {
        return $this->typeValue($value, $this->itemType)->normalizeValue($value);
    }

    /**
     * @param string $type
     * @return $this
     */
    public function itemType($type)
    {
        $this->itemType = $type;
        return $this;
    }

    /**
     * @inherit
     */
    public function toYdbValue()
    {
         return $this->typeValue($this->value, $this->itemType)->toYdbValue();
    }

    /**
     * @inherit
     */
    public function getYdbType()
    {
        $type_id = $this->convertType($this->itemType);

        if ($type_id)
        {
            return new Type([
                'optional_type' => new \Ydb\OptionalType([
                    'item' => new Type([
                        'type_id' => $type_id,
                    ]),
                ]),
            ]);
        }
        else
        {
            $value = $this->typeValue('', $this->itemType);
            return new Type([
                'optional_type' => new \Ydb\OptionalType([
                    'item' => $value->getYdbType(),
                ]),
            ]);
        }
    }

    /**
     * @inherit
     */
    public function toYdbType()
    {
        return $this->getYdbType();
    }

    /**
     * @inherit
     */
    protected function getYqlString()
    {
        $value = $this->typeValue($this->value, $this->itemType)->toYqlString();

        return '(' . $value . ')';
    }

}

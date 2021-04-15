<?php

namespace YandexCloud\Ydb\Types;

use Ydb\Type;
use Ydb\Value;
use Ydb\ListType as YdbListType;

class ListType extends AbstractType
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
        return (array)$value;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function itemType($type)
    {
        $this->itemType = strtoupper($type);
        return $this;
    }

    /**
     * @inherit
     */
    public function toYdbValue()
    {
        if ($this->value === null)
        {
            return new Value(['items' => []]);
        }

        return new Value([
            'items' => array_map(function($item) {
                return $this->typeValue($item, $this->itemType)->toYdbValue();
            }, $this->value),
        ]);
    }

    /**
     * @inherit
     */
    public function getYdbType()
    {
        return new Type([
            'list_type' => new YdbListType([
                'item' => new Type([
                    'type_id' => $this->convertType($this->itemType),
                ]),
            ]),
        ]);
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
        $value = implode(', ', array_map(function($item) {
            return $this->typeValue($item, $this->itemType)->toYqlString();
        }, $this->value));

        return '(' . $value . ')';
    }
}

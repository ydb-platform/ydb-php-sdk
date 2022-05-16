<?php

namespace YdbPlatform\Ydb\Types;

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
        $this->itemType = $type;
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
        $type_id = $this->convertType($this->itemType);

        if ($type_id)
        {
            return new Type([
                'list_type' => new YdbListType([
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
                'list_type' => new YdbListType([
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
        $value = implode(', ', array_map(function($item) {
            return $this->typeValue($item, $this->itemType)->toYqlString();
        }, $this->value));

        return '(' . $value . ')';
    }
}

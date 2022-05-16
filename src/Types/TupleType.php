<?php

namespace YdbPlatform\Ydb\Types;

use Ydb\Type;
use Ydb\Value;
use Ydb\TupleType as YdbTupleType;
use Ydb\OptionalType;
use YdbPlatform\Ydb\Contracts\TypeContract;

class TupleType extends AbstractType
{
    /**
     * @var array
     */
    protected $itemTypes = [];

    /**
     * @param string|array $types
     * @return $this
     */
    public function itemTypes($types)
    {
        if (is_array($types))
        {
            $this->itemTypes = $types;
        }
        else
        {
            $this->itemTypes = [];
            $types = array_map('trim', explode(',', $types));
            foreach ($types as $type)
            {
                $this->itemTypes[] = $type;
            }
        }
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
            'items' => $this->convertValue(),
        ]);
    }

    /**
     * @inherit
     */
    public function getYdbType()
    {
        return new Type([
            'tuple_type' => new YdbTupleType([
                'elements' => $this->convertToElements(),
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
            return $item->toYqlString();
        }, $this->convertValue()));

        return '(' . $value . ')';
    }


    protected function convertToElements()
    {
        $elements = [];

        foreach ($this->itemTypes as $type)
        {
            $elements[] = new Type([
                'type_id' => $this->convertType($type),
                'optional_type' => new OptionalType([
                    'item' => new Type([
                        'type_id' => $this->convertType($type),
                    ]),
                ]),
            ]);
        }

        return $elements;
    }

    protected function convertValue()
    {
        if ($this->value)
        {
            $items = (array)$this->value;

            $data = [];

            foreach ($this->itemTypes as $i => $type)
            {
                if (isset($items[$i]))
                {
                    $data[] = $this->typeValue($items[$i], $type)->toYdbValue();
                }
            }

            return $data;
        }
        else
        {
            return $this->value;
        }
    }
}

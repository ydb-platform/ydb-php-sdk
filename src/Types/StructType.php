<?php

namespace YdbPlatform\Ydb\Types;

use Ydb\Type;
use Ydb\Value;
use Ydb\StructType as YdbStructType;
use Ydb\StructMember;

class StructType extends AbstractType
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
                list($name, $type) = array_map('trim', explode(':', $type));
                $this->itemTypes[$name] = $type;
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
            'struct_type' => new YdbStructType([
                'members' => $this->convertToStructMember(),
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

    protected function convertToStructMember()
    {
        $members = [];

        foreach ($this->itemTypes as $name => $type)
        {
            $members[] = new StructMember([
                'name' => $name,
                'type' => new Type([
                    'type_id' => $this->convertType($type),
                ]),
            ]);
        }

        return $members;
    }

    protected function convertValue()
    {
        if ($this->value)
        {
            $items = (array)$this->value;

            $data = [];

            foreach ($this->itemTypes as $name => $type)
            {
                if (isset($items[$name]))
                {
                    $value = $this->typeValue($items[$name], $type);
                    $data[$name] = $value->toYdbValue();
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

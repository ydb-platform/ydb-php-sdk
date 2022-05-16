<?php

namespace YdbPlatform\Ydb\Traits;

trait ParseResultTrait
{
    /**
     * @param object $result
     * @param array|null $properties
     * @param mixed $default
     * @return array|mixed|null
     */
    protected function parseResult($result, $properties = null, $default = null)
    {
        $result = json_decode($result->serializeToJsonString(), true);

        $parsedResult = [];

        if (is_array($properties))
        {
            foreach ($properties as $property)
            {
                $parsedResult[$property] = $result[$property] ?? null;
            }
        }
        else if (is_string($properties))
        {
            $parsedResult = $result[$properties] ?? $default;
        }
        else
        {
            $parsedResult = $result;
        }

        return $parsedResult;
    }
}

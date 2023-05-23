<?php

namespace YdbPlatform\Ydb;

use Exception;

use Ydb\TypedValue;
use Ydb\Table\Query;

class Statement
{
    use Traits\TypeHelpersTrait;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var string
     */
    protected $yql;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @param Session $session
     * @param string $yql
     */
    public function __construct(Session $session, $yql)
    {
        $this->session = $session;
        $this->yql = $yql;

        $this->detectParams();
    }

    /**
     * @param array $parameters
     * @return bool|QueryResult
     * @throws \YdbPlatform\Ydb\Exception
     */
    public function execute(array $parameters = [])
    {
        $q = new Query([
            'yql_text' => $this->yql,
        ]);

        return $this->session->query($q, $this->prepareParameters($parameters));
    }

    /**
     * @param array $parameters
     * @return array
     * @throws Exception
     */
    protected function prepareParameters($parameters)
    {
        $data = [];
        foreach ($parameters as $key => $value)
        {
            if (substr($key, 0, 1) !== '$')
            {
                $key = '$' . $key;
            }

            if (!isset($this->params[$key]))
            {
                throw new Exception('YDB: Statement parameter [' . $key . '] not declared.');
            }

            if (!is_a($value, TypedValue::class))
            {
                $value = $this->typeValue($value, $this->params[$key])->toTypedValue();
            }
            $data[$key] = $value;
        }
        return $data;
    }

    /**
     * @return void
     */
    protected function detectParams()
    {
        if (preg_match_all('/declare\s+(\$\w+)\s+as\s+(.+?);/is', $this->yql, $matches))
        {
            foreach ($matches[1] as $i => $param)
            {
                $type = trim($matches[2][$i]);
                $this->params[$param] = $type;
            }
        }
    }
}

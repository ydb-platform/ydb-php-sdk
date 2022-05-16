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
     * @var string
     */
    protected $query_id;

    /**
     * @var string
     */
    protected $qhash;

    /**
     * @var bool
     */
    protected $cached = false;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var array
     */
    protected static $qcache = [];

    /**
     * @param Session $session
     * @param string $yql
     */
    public function __construct(Session $session, $yql)
    {
        $this->session = $session;
        $this->yql = $yql;

        $this->checkQueryCache();
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
            'id' => $this->query_id,
        ]);

        return $this->session->query($q, $this->prepareParameters($parameters));
    }

    /**
     * @return bool
     */
    public function isCached()
    {
        return $this->cached;
    }

    /**
     * @return string
     */
    public function getQueryId()
    {
        return $this->query_id;
    }

    /**
     * @param string $query_id
     */
    public function saveQueryId($query_id)
    {
        $this->query_id = $query_id;
        static::$qcache[$this->qhash] = $this->query_id;
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
    protected function checkQueryCache()
    {
        $this->qhash = sha1($this->session->id() . '~' . trim($this->yql));
        $this->query_id = static::$qcache[$this->qhash] ?? null;
        if ($this->query_id)
        {
            $this->cached = true;
        }
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

<?php

namespace YdbPlatform\Ydb;

class Cluster
{
    /**
     * @var Ydb
     */
    protected $ydb;

    /**
     * @var array
     */
    protected $connections = [];

    /**
     * @var array
     */
    protected $rating = [];

    /**
     * @param Ydb $ydb
     */
    public function __construct(Ydb $ydb)
    {
        $this->ydb = $ydb;
    }

    /**
     * @return Connection|null
     */
    public function get()
    {
        $id = array_shift($this->rating);

        if ($id)
        {
            $connection = $this->find($id);
            if ($connection)
            {
                $connection->use();
                return $connection;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->connections;
    }

    /**
     * @param string $id
     * @return Connection|null
     */
    public function find($id)
    {
        return $this->connections[$id] ?? null;
    }

    /**
     * @param array $endpoints
     * @return void
     */
    public function sync(array $endpoints)
    {
        $keys = array_keys($this->connections);
        $current = array_combine($keys, $keys);

        foreach ((array)$endpoints as $endpoint)
        {
            if ($id = $this->insert($endpoint))
            {
                if (isset($current[$id]))
                {
                    unset($current[$id]);
                }
            }
        }

        foreach ($current as $id)
        {
            $this->remove($this->find($id));
        }

        $this->calcRating();
    }

    /**
     * @param array $endpoint
     * @return string|null
     */
    public function insert(array $endpoint)
    {
        $connection = new Connection($endpoint);

        if ($connection->id())
        {
            if (isset($this->connections[$connection->id()]))
            {
                $this->connections[$connection->id()]->update($endpoint);
            }
            else
            {
                $this->connections[$connection->id()] = $connection;
            }
            return $connection->id();
        }

        return null;
    }

    /**
     * @param mixed $connection
     * @return void
     */
    public function remove($connection)
    {
        if (!is_a($connection, Connection::class))
        {
            $connection = new Connection($connection);
        }

        if ($connection->id())
        {
            if (isset($this->connections[$connection->id()]))
            {
                unset($this->connections[$connection->id()]);
            }
        }
    }

    /**
     * @return void
     */
    protected function calcRating()
    {
        $connections = array_map(
            function($connection) {
                return $connection->toArray();
            },
            $this->connections
        );

        uasort($connections, function($a, $b) {
            $r = 0;

            if ($a['priority'] === $b['priority'])
            {
                if ($a['load_factor'] === $b['load_factor'])
                {
                    if ($a['last_used'] != $b['last_used'])
                    {
                        $r = ($a['last_used'] < $b['last_used']) ? -1 : 1;
                    }
                }
                else
                {
                    $r = ($a['load_factor'] < $b['load_factor']) ? -1 : 1;
                }
            }
            else
            {
                $r = ($a['priority'] > $b['priority']) ? -1 : 1;
            }

            return $r;
        });

        $this->rating = array_keys($connections);
    }

}

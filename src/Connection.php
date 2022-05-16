<?php

namespace YdbPlatform\Ydb;

class Connection
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $address;

    /**
     * @var string
     */
    protected $port;

    /**
     * @var bool
     */
    protected $ssl = false;

    /**
     * @var array
     */
    protected $service = [];

    /**
     * @var string
     */
    protected $location;

    /**
     * @var int
     */
    protected $load_factor = 0;

    /**
     * @var int
     */
    protected $priority = 0;

    /**
     * @var int
     */
    protected $created = 0;

    /**
     * @var int
     */
    protected $updated = 0;

    /**
     * @var int
     */
    protected $last_used = 0;

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        if (!empty($data) && is_array($data))
        {
            $this->fill($data);
            $this->id();
        }
        $this->created = time();
        $this->updated = time();
    }

    /**
     * @param array $data
     */
    public function update(array $data)
    {
        if (isset($data['id']))
        {
            if ($data['id'] === $this->id())
            {
                $this->fill($data);
                $this->updated = time();
            }
        }
    }

    /**
     * @return string
     */
    public function id()
    {
        if (!isset($this->id))
        {
            $this->generateId();
        }

        return $this->id;
    }

    /**
     * @return string
     */
    public function address()
    {
        return $this->address;
    }

    /**
     * @return string
     */
    public function port()
    {
        return $this->port;
    }

    /**
     * @return bool
     */
    public function ssl()
    {
        return $this->ssl;
    }

    /**
     * @return string
     */
    public function location()
    {
        return $this->location;
    }

    /**
     * @return array
     */
    public function service()
    {
        return $this->service;
    }

    /**
     * @return int
     */
    public function loadFactor()
    {
        return $this->load_factor;
    }

    /**
     * @return int
     */
    public function priority()
    {
        return $this->priority;
    }

    /**
     * @return void
     */
    public function upgradePriority()
    {
        $this->priority += 1;
    }

    /**
     * @return void
     */
    public function degradePriority()
    {
        $this->priority -= 1;
    }

    /**
     * @return int
     */
    public function created()
    {
        return $this->created;
    }

    /**
     * @return int
     */
    public function updated()
    {
        return $this->updated;
    }

    /**
     * @return int
     */
    public function last_used()
    {
        return $this->last_used;
    }

    /**
     * @return void
     */
    public function use()
    {
        $this->last_used = time();
    }

    /**
     * @return string|null
     */
    public function endpoint()
    {
        if (isset($this->address, $this->port))
        {
            return $this->address . ':' . $this->port;
        }
        return null;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->id(),
            'address' => $this->address(),
            'port' => $this->port(),
            'ssl' => $this->ssl(),
            'service' => $this->service(),
            'location' => $this->location(),
            'load_factor' => $this->loadFactor(),
            'priority' => $this->priority(),
            'created' => $this->created(),
            'updated' => $this->updated(),
            'last_used' => $this->last_used(),
        ];
    }

    /**
     * @param array $data
     */
    protected function fill(array $data)
    {
        if (isset($data['address']))
        {
            $this->address = $data['address'];
        }

        if (isset($data['port']))
        {
            $this->port = $data['port'];
        }

        if (isset($data['ssl']))
        {
            $this->ssl = (bool)($data['ssl']);
        }

        if (isset($data['service']))
        {
            $this->service = (array)$data['service'];
        }

        if (isset($data['location']))
        {
            $this->location = $data['location'];
        }

        if (isset($data['load_factor']))
        {
            $this->load_factor = (float)$data['load_factor'];
        }
    }

    /**
     * @return void
     */
    protected function generateId()
    {
        if (isset($this->address, $this->port))
        {
            $this->id = md5($this->address . ':' . $this->port);
        }
    }


}

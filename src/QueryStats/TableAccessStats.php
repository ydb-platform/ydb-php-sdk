<?php
namespace YdbPlatform\Ydb\QueryStats;
class TableAccessStats
{

    /**
     * @var \Ydb\TableStats\TableAccessStats
     */
    protected $accessStats;

    public function __construct(\Ydb\TableStats\TableAccessStats $accessStats)
    {
        $this->accessStats = $accessStats;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->accessStats->getName();
    }

    /**
     * @return OperationStats|null
     */
    public function getReads()
    {
        $result = $this->accessStats->getReads();
        if ($result){
            $result = new OperationStats($result);
        }
        return $result;
    }

    public function hasReads()
    {
        return $this->accessStats->hasReads();
    }

    public function clearReads()
    {
        $this->accessStats->clearReads();
    }

    /**
     * @return OperationStats|null
     */
    public function getUpdates()
    {
        $result = $this->accessStats->getUpdates();
        if ($result){
            $result = new OperationStats($result);
        }
        return $result;
    }

    public function hasUpdates()
    {
        return $this->accessStats->hasUpdates();
    }

    public function clearUpdates()
    {
        $this->accessStats->clearUpdates();
    }


    /**
     * @return \Ydb\TableStats\OperationStats|null
     */
    public function getDeletes()
    {
        return $this->accessStats->getDeletes();
    }

    public function hasDeletes()
    {
        return $this->accessStats->hasDeletes();
    }

    public function clearDeletes()
    {
        $this->accessStats->clearDeletes();
    }

    /**
     * @return int|string
     */
    public function getPartitionsCount()
    {
        return $this->accessStats->getPartitionsCount();
    }
}


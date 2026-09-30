<?php

namespace YdbPlatform\Ydb;

use Ydb\Query\ExecuteQueryResponsePart;

class QueryExecutionResult
{
    /** @var ExecuteQueryResponsePart[] */
    private $parts;
    /** @var CommitTimestamp|null */
    private $commitTimestamp;

    /** @param ExecuteQueryResponsePart[] $parts */
    public function __construct(array $parts, Ydb $origin)
    {
        $this->parts = $parts;
        $last = end($parts);
        // The service defines commit_timestamp only on the final trailing part.
        $this->commitTimestamp = $last && $last->hasCommitTimestamp()
            ? new CommitTimestamp($last->getCommitTimestamp(), $origin)
            : null;
    }

    /** @return ExecuteQueryResponsePart[] */
    public function parts(): array
    {
        return $this->parts;
    }

    public function commitTimestamp(): ?CommitTimestamp
    {
        return $this->commitTimestamp;
    }
}

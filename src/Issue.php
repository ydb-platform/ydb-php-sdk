<?php

namespace YandexCloud\Ydb;

use Ydb\Issue\IssueMessage;

class Issue
{
    /**
     * @var \Ydb\Issue\IssueMessage
     */
    protected $issue;

    public function __construct(IssueMessage $issue)
    {
        $this->issue = $issue;
    }

    /**
     * @return string
     */
    public function toString()
    {
        $msg = trim($this->issue->getMessage());
        if (count($this->issue->getIssues()))
        {
            $msg .= ':';
        }

        $msgs = [$msg];
        $issues = static::getSubIssues($this->issue);
        if ($issues)
        {
            array_push($msgs, ...$issues);
        }

        return implode("\n", $msgs);
    }

    /**
     * @param \Ydb\Issue\IssueMessage $issue
     * @param int $level
     * @return array
     */
    protected static function getSubIssues(IssueMessage $issue, $level = 0)
    {
        $msgs = [];
        foreach ($issue->getIssues() as $sub_issue)
        {
            $msg = str_repeat('  ', $level) . '- ' . trim($sub_issue->getMessage());
            $msgs[] = $msg;
            $issues = static::getSubIssues($sub_issue, $level + 1);
            if ($issues)
            {
                array_push($msgs, ...$issues);
            }
        }
        return $msgs;
    }
}

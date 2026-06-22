<?php

namespace Tests\Mocks;

class FakePullRequest extends \PullRequest
{
    public function __construct(
        private string $pullRequestUrl = 'https://github.com/mockorg/mockrepo/pull/1',
        private string $branchUrl = 'https://github.com/mockorg/mockrepo/tree/feature-branch',
        private string $pullRequestOrigin = 'mockorg:mockrepo:feature-branch',
        private bool $closed = false,
        private bool $merged = false,
        private string $merger = '',
        private ?\DateTimeImmutable $mergedAt = null,
        private int $addedLines = 0,
        private int $deletedLines = 0,
        private int $modifiedFiles = 0,
        private array $failedJobs = []
    ) {}

    public function url(): string
    {
        return $this->pullRequestUrl;
    }

    public function branchURL(): string
    {
        return $this->branchUrl;
    }

    public function origin(): string
    {
        return $this->pullRequestOrigin;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function wasMerged(): bool
    {
        return $this->merged;
    }

    public function mergedBy(): string
    {
        return $this->merger;
    }

    public function mergeDate(): \DateTimeImmutable
    {
        return $this->mergedAt ?? new \DateTimeImmutable();
    }

    public function linesAdded(): int
    {
        return $this->addedLines;
    }

    public function linesDeleted(): int
    {
        return $this->deletedLines;
    }

    public function filesModified(): int
    {
        return $this->modifiedFiles;
    }

    public function failedCIjobs(string $hash): array
    {
        return $this->failedJobs;
    }

    public function __toString(): string
    {
        return $this->pullRequestUrl;
    }
}

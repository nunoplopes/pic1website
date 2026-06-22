<?php

namespace Tests\Mocks;

class FakePatch extends \Patch
{
    public function __construct(
        private bool $valid = true,
        private string $patchBranch = 'feature-branch',
        private string $patchOrigin = 'origin/feature-branch',
        private array $patchCommits = [],
        private array $patchDiff = [],
        private string $patchText = 'patch content',
        private string $branchHash = 'abc123',
        private int $computedLinesAdded = 10,
        private int $computedLinesDeleted = 5,
        private int $computedFilesModified = 3,
        private string $patchUrl = 'https://github.com/test',
        private string $commitUrlBase = 'https://github.com/test/commit/',
        private ?\PullRequest $pullRequest = null,
        private bool $setsPullRequest = false
    ) {
        parent::__construct();
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function branch(): string
    {
        return $this->patchBranch;
    }

    public function origin(): string
    {
        return $this->patchOrigin;
    }

    public function commits(): array
    {
        return $this->patchCommits;
    }

    public function diff(): array
    {
        return $this->patchDiff;
    }

    public function patch(): string
    {
        return $this->patchText;
    }

    protected function computeBranchHash(): string
    {
        return $this->branchHash;
    }

    protected function computeLinesAdded(): int
    {
        return $this->computedLinesAdded;
    }

    protected function computeLinesDeleted(): int
    {
        return $this->computedLinesDeleted;
    }

    protected function computeFilesModified(): int
    {
        return $this->computedFilesModified;
    }

    public function getPatchURL(): string
    {
        return $this->patchUrl;
    }

    public function getCommitURL(string $hash): string
    {
        return $this->commitUrlBase . $hash;
    }

    public function setPR(\PullRequest $pr)
    {
        $this->pullRequest = $pr;
    }

    public function findAndSetPR(): bool
    {
        return $this->setsPullRequest;
    }

    public function getPR(): ?\PullRequest
    {
        return $this->pullRequest;
    }
}

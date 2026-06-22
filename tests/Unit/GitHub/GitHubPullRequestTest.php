<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;

use GitHub\GitHubPullRequest;


class GitHubPullRequestTest extends UnitTestCase
{
    public function testPullRequestMetadataComesFromGitHub()
    {
        $this->mockGitHubClient();
        $pr = new GitHubPullRequest(new \Repository('github:owner/repo'), 123);

        $this->assertEquals(123, $pr->getNumber());
        $this->assertEquals('https://github.com/owner/repo/pull/123', $pr->url());
        $this->assertEquals('https://github.com/fork/repo/tree/feature-branch', $pr->branchURL());
        $this->assertEquals('fork:repo:feature-branch', $pr->origin());
        $this->assertTrue($pr->isClosed());
        $this->assertEquals('merge-user', $pr->mergedBy());
        $this->assertEquals('2026-04-26T10:00:00+00:00', $pr->mergeDate()->format('c'));
        $this->assertEquals(12, $pr->linesAdded());
        $this->assertEquals(3, $pr->linesDeleted());
        $this->assertEquals(2, $pr->filesModified());
        $this->assertEquals('GitHub PR owner/repo#123', (string)$pr);
    }

    public function testWasMergedReturnsTrueWhenGithubMarksMerged()
    {
        $this->mockGitHubClient(merged: true);
        $pr = new GitHubPullRequest(new \Repository('github:owner/repo'), 123);

        $this->assertTrue($pr->wasMerged());
    }

    public function testWasMergedDetectsBotMergeCommentWithLabel()
    {
        $this->mockGitHubClient(
            merged: false,
            state: 'closed',
            comments: [[
                'user' => ['login' => 'pytorchmergebot'],
                'body' => "### Merge started\nThis pull request was merged by automation.",
            ]],
            labels: [['name' => 'Merged']]
        );
        $pr = new GitHubPullRequest(new \Repository('github:owner/repo'), 123);

        $this->assertTrue($pr->wasMerged());
    }

    public function testWasMergedReturnsFalseForClosedUnmergedPullRequest()
    {
        $this->mockGitHubClient(
            merged: false,
            state: 'closed',
            comments: [[
                'user' => ['login' => 'someone'],
                'body' => 'Regular comment.',
            ]],
            labels: []
        );
        $pr = new GitHubPullRequest(new \Repository('github:owner/repo'), 123);

        $this->assertFalse($pr->wasMerged());
        $this->assertFalse($pr->has_label('Merged'));
    }

    public function testCommentsAndLabelsAreLoadedFromIssueApi()
    {
        $comments = [['user' => ['login' => 'alice'], 'body' => 'Looks good.']];
        $labels = [['name' => 'Approved'], ['name' => 'Merged']];
        $this->mockGitHubClient(comments: $comments, labels: $labels);
        $pr = new GitHubPullRequest(new \Repository('github:owner/repo'), 123);

        $this->assertEquals($comments, $pr->comments());
        $this->assertEquals($labels, $pr->labels());
        $this->assertTrue($pr->has_label('Approved'));
    }

    public function testFailedCIJobsReturnsStatusesAndWorkflowFailures()
    {
        $this->mockGitHubClient();
        $pr = new GitHubPullRequest(new \Repository('github:owner/repo'), 123);

        $failed = $pr->failedCIjobs('abc123');

        $this->assertCount(2, $failed);
        $this->assertEquals('lint', $failed[0]['name']);
        $this->assertEquals('https://ci.example.com/lint', $failed[0]['url']);
        $this->assertEquals('unit-tests', $failed[1]['name']);
        $this->assertEquals('https://github.com/owner/repo/actions/jobs/1', $failed[1]['url']);
    }

    public function testFailedCIJobsIgnoresGitHubRuntimeErrors()
    {
        $this->mockGitHubClient(throwOnCi: true);
        $pr = new GitHubPullRequest(new \Repository('github:owner/repo'), 123);

        $this->assertEquals([], $pr->failedCIjobs('abc123'));
    }

    private function mockGitHubClient(
        bool $merged = false,
        string $state = 'closed',
        array $comments = [],
        array $labels = [],
        bool $throwOnCi = false
    ): void {
        $this->replaceGitHubClient(new class($merged, $state, $comments, $labels, $throwOnCi) {
            public function __construct(
                private bool $merged,
                private string $state,
                private array $comments,
                private array $labels,
                private bool $throwOnCi
            ) {}

            public function api($endpoint) {
                return match ($endpoint) {
                    'pr' => new class($this->merged, $this->state) {
                        public function __construct(private bool $merged, private string $state) {}

                        public function show($org, $repo, $number) {
                            return [
                                'state' => $this->state,
                                'merged' => $this->merged,
                                'merged_by' => ['login' => 'merge-user'],
                                'merged_at' => '2026-04-26T10:00:00+00:00',
                                'additions' => 12,
                                'deletions' => 3,
                                'changed_files' => 2,
                                'head' => [
                                    'repo' => ['full_name' => 'fork/repo'],
                                    'ref' => 'feature-branch',
                                ],
                            ];
                        }
                    },
                    'issue' => new class($this->comments, $this->labels) {
                        public function __construct(private array $comments, private array $labels) {}

                        public function comments() {
                            return new class($this->comments) {
                                public function __construct(private array $comments) {}
                                public function all($org, $repo, $number) { return $this->comments; }
                            };
                        }

                        public function labels() {
                            return new class($this->labels) {
                                public function __construct(private array $labels) {}
                                public function all($org, $repo, $number) { return $this->labels; }
                            };
                        }
                    },
                    'repo' => new class($this->throwOnCi) {
                        public function __construct(private bool $throwOnCi) {}

                        public function statuses() {
                            return new class($this->throwOnCi) {
                                public function __construct(private bool $throwOnCi) {}

                                public function combined($org, $repo, $hash) {
                                    if ($this->throwOnCi) {
                                        throw new \Github\Exception\RuntimeException('temporary');
                                    }
                                    return [
                                        'statuses' => [
                                            [
                                                'state' => 'failure',
                                                'context' => 'lint',
                                                'target_url' => 'https://ci.example.com/lint',
                                                'updated_at' => '2026-04-26T10:00:00+00:00',
                                            ],
                                            [
                                                'state' => 'success',
                                                'context' => 'style',
                                                'target_url' => 'https://ci.example.com/style',
                                                'updated_at' => '2026-04-26T10:00:00+00:00',
                                            ],
                                        ],
                                    ];
                                }
                            };
                        }

                        public function workflowRuns() {
                            return new class {
                                public function all($org, $repo, $params) {
                                    return [
                                        'workflow_runs' => [
                                            ['id' => 10, 'conclusion' => 'failure'],
                                            ['id' => 11, 'conclusion' => 'success'],
                                        ],
                                    ];
                                }
                            };
                        }

                        public function workflowJobs() {
                            return new class {
                                public function all($org, $repo, $runId) {
                                    return [
                                        'jobs' => [
                                            [
                                                'name' => 'unit-tests',
                                                'html_url' => 'https://github.com/owner/repo/actions/jobs/1',
                                                'completed_at' => '2026-04-26T10:01:00+00:00',
                                                'conclusion' => 'failure',
                                            ],
                                            [
                                                'name' => 'build',
                                                'html_url' => 'https://github.com/owner/repo/actions/jobs/2',
                                                'completed_at' => '2026-04-26T10:02:00+00:00',
                                                'conclusion' => 'success',
                                            ],
                                        ],
                                    ];
                                }
                            };
                        }
                    },
                };
            }
        });
    }
}

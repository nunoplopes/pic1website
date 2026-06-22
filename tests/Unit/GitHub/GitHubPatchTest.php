<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;

use GitHub\GitHubPatch;


class GitHubPatchTest extends UnitTestCase
{
    public function testConstructSupportsCompareUrlWithoutSourceBranch()
    {
        $this->mockGitHubClient();
        $patch = GitHubPatch::construct(
            'https://github.com/owner/repo/compare/fork:repo:feature',
            new \Repository('github:owner/repo')
        );

        $this->assertEquals('fork:repo:feature', $patch->repo_branch);
        $this->assertEquals('', $patch->src_branch);
    }

    public function testConstructSupportsTreeUrl()
    {
        $this->mockGitHubClient();
        $patch = GitHubPatch::construct(
            'https://github.com/fork/repo/tree/feature',
            new \Repository('github:owner/repo')
        );

        $this->assertEquals('fork:repo:feature', $patch->repo_branch);
        $this->assertEquals('', $patch->src_branch);
    }

    public function testConstructRejectsMismatchedCompareRepository()
    {
        $this->expectException(\ValidationException::class);
        $this->expectExceptionMessage("Patch is not for Project's repository");

        GitHubPatch::construct(
            'https://github.com/other/repo/compare/main...fork:repo:feature',
            new \Repository('github:owner/repo')
        );
    }

    public function testConstructRejectsMismatchedCompareRepositoryWithoutSourceBranch()
    {
        $this->expectException(\ValidationException::class);
        $this->expectExceptionMessage("Patch is not for Project's repository");

        GitHubPatch::construct(
            'https://github.com/other/repo/compare/fork:repo:feature',
            new \Repository('github:owner/repo')
        );
    }

    public function testConstructRejectsUnknownUrlFormat()
    {
        $this->expectException(\ValidationException::class);
        $this->expectExceptionMessage('Unknown patch URL format');

        GitHubPatch::construct(
            'https://github.com/owner/repo/pull/1',
            new \Repository('github:owner/repo')
        );
    }

    public function testConstructRejectsUnparseableCommitUrl()
    {
        $this->mockGitHubClient(branchCommitUrl: 'https://example.com/not-github');

        $this->expectException(\ValidationException::class);
        $this->expectExceptionMessage("Couldn't parse github commit URL");

        GitHubPatch::construct(
            'https://github.com/owner/repo/tree/feature',
            new \Repository('github:owner/repo')
        );
    }

    public function testConstructRejectsMissingBranch()
    {
        $this->mockGitHubClient(branchException: true);

        $this->expectException(\ValidationException::class);
        $this->expectExceptionMessage('Non-existent patch');

        GitHubPatch::construct(
            'https://github.com/owner/repo/tree/missing',
            new \Repository('github:owner/repo')
        );
    }

    public function testIsValidHandlesGitHubRuntimeExceptions()
    {
        $patch = $this->patchFixture(srcBranch: 'main');

        $this->mockGitHubClient(compareExceptionMessage: 'Not Found');
        $this->assertFalse($patch->isValid());

        $this->mockGitHubClient(compareExceptionMessage: 'temporary');
        $this->assertTrue($patch->isValid());
    }

    public function testSrcBranchFallsBackToRepositoryDefaultBranch()
    {
        $this->replaceCachedGitHubClient(new class {
            public function api($endpoint) {
                return new class {
                    public function show($owner, $repo) {
                        return ['default_branch' => 'develop'];
                    }
                };
            }
        });
        $patch = $this->patchFixture();

        $this->assertEquals('develop', $patch->srcBranch());
    }

    public function testCompareDataIsMappedIntoPatchDetailsAndStats()
    {
        $this->mockGitHubClient(
            compareData: [
                'commits' => [
                    [
                        'sha' => 'firsthash',
                        'author' => ['login' => 'student'],
                        'commit' => [
                            'author' => [
                                'name' => 'Student User',
                                'email' => 'ist12345@tecnico.ulisboa.pt',
                            ],
                            'message' => "Implement first change.\nCo-authored-by: Pair User <pair@example.com>",
                        ],
                    ],
                    [
                        'sha' => 'lasthash',
                        'author' => null,
                        'commit' => [
                            'author' => [
                                'name' => 'Other User',
                                'email' => 'other@example.com',
                            ],
                            'message' => 'Implement second change.',
                        ],
                    ],
                ],
                'files' => [
                    ['filename' => 'src/a.php', 'patch' => '+one', 'additions' => 3, 'deletions' => 1],
                    ['filename' => 'src/b.php', 'additions' => 7, 'deletions' => 2],
                ],
            ],
            patchText: "diff --git a/src/a.php b/src/a.php\n+one\n"
        );

        $student = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $student->repository_user = 'github:student';

        $patch = $this->patchFixture(srcBranch: 'main');
        $patch->group->addStudent($student);

        $this->assertEquals('feature', $patch->branch());
        $this->assertEquals('fork:repo:feature', $patch->origin());
        $this->assertSame([
            [
                'username' => 'student',
                'name' => 'Student User',
                'email' => 'ist12345@tecnico.ulisboa.pt',
                'message' => "Implement first change.\nCo-authored-by: Pair User <pair@example.com>",
                'hash' => 'firsthash',
                'co-authored' => [['', 'Pair User', 'pair@example.com']],
            ],
            [
                'username' => '',
                'name' => 'Other User',
                'email' => 'other@example.com',
                'message' => 'Implement second change.',
                'hash' => 'lasthash',
                'co-authored' => [],
            ],
        ], $patch->commits());
        $this->assertSame([
            ['filename' => 'src/a.php', 'patch' => '+one'],
            ['filename' => 'src/b.php', 'patch' => ''],
        ], $patch->diff());
        $this->assertSame("diff --git a/src/a.php b/src/a.php\n+one\n", $patch->patch());

        $patch->updateStats();

        $this->assertEquals('lasthash', $patch->hash);
        $this->assertEquals(10, $patch->lines_added);
        $this->assertEquals(3, $patch->lines_deleted);
        $this->assertEquals(2, $patch->files_modified);
        $this->assertTrue($patch->students->contains($student));
    }

    public function testUrlsAndSetPR()
    {
        $patch = $this->patchFixture(srcBranch: 'base branch');

        $pr = new class extends \PullRequest {
            public function getNumber() { return 77; }
            public function url(): string { return ''; }
            public function branchURL(): string { return ''; }
            public function origin(): string { return ''; }
            public function isClosed(): bool { return false; }
            public function wasMerged(): bool { return false; }
            public function mergedBy(): string { return ''; }
            public function mergeDate(): \DateTimeImmutable { return new \DateTimeImmutable(); }
            public function linesAdded(): int { return 0; }
            public function linesDeleted(): int { return 0; }
            public function filesModified(): int { return 0; }
            public function failedCIjobs(string $hash): array { return []; }
            public function __toString() { return ''; }
        };

        $patch->setPR($pr);

        $this->assertEquals(77, $patch->pr_number);
        $this->assertEquals(
            'https://github.com/owner/repo/compare/base+branch...fork%3Arepo%3Afeature',
            $patch->getPatchURL()
        );
        $this->assertEquals(
            'https://github.com/owner/repo/commit/abc123',
            $patch->getCommitURL('abc123')
        );
        $this->assertInstanceOf(\GitHub\GitHubPullRequest::class, $patch->getPR());
    }

    public function testFindAndSetPRForFeaturePatch()
    {
        $student = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $student->repository_user = 'github:student';

        $patch = $this->patchFixture();
        $patch->type = \PatchType::Feature;
        $patch->hash = 'abc123';
        $patch->group->addStudent($student);

        $this->mockGitHubClient(pulls: [
            [
                'html_url' => 'https://github.com/owner/repo/pull/4',
                'user' => ['login' => 'student'],
                'number' => 4,
            ],
        ]);

        $this->assertTrue($patch->findAndSetPR());
        $this->assertEquals(4, $patch->pr_number);
    }

    public function testFindAndSetPRAddsCommentWhenPRChanges()
    {
        $student = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $student->repository_user = 'github:student';

        $patch = $this->patchFixture();
        $patch->type = \PatchType::Feature;
        $patch->hash = 'abc123';
        $patch->pr_number = 4;
        $patch->group->addStudent($student);

        $this->mockGitHubClient(pulls: [
            [
                'html_url' => 'https://github.com/owner/repo/pull/9',
                'user' => ['login' => 'student'],
                'number' => 9,
            ],
        ]);

        $this->assertTrue($patch->findAndSetPR());
        $this->assertEquals(9, $patch->pr_number);
        $this->assertCount(1, $patch->comments);
        $this->assertEquals(
            "PR updated: 4 \u{2192} 9",
            $patch->comments->first()->text
        );
    }

    public function testFindAndSetPRForBugFixPatchAndRuntimeException()
    {
        $submitter = new \User('student', 'Student User', 'student@example.com', '', ROLE_STUDENT, false);
        $submitter->repository_user = 'github:student';

        $patch = $this->patchFixture();
        $patch->type = \PatchType::BugFix;
        $patch->hash = 'abc123';
        $patch->comments->add(new \PatchComment($patch, 'Submitted', $submitter));

        $this->mockGitHubClient(pulls: [
            [
                'html_url' => 'https://github.com/owner/repo/pull/5',
                'user' => ['login' => 'student'],
                'number' => 5,
            ],
        ]);

        $this->assertTrue($patch->findAndSetPR());
        $this->assertEquals(5, $patch->pr_number);

        $this->mockGitHubClient(pullsException: true);
        $this->assertFalse($patch->findAndSetPR());
    }

    private function patchFixture(string $srcBranch = ''): GitHubPatch
    {
        $shift = new \Shift('T01', 2026);
        $group = new \ProjGroup(1, 2026, $shift);
        $group->repository = 'github:owner/repo';

        $patch = new GitHubPatch();
        $patch->group = $group;
        $patch->repo_branch = 'fork:repo:feature';
        $patch->src_branch = $srcBranch;

        return $patch;
    }

    private function mockGitHubClient(
        string $branchCommitUrl = 'https://api.github.com/repos/fork/repo/commits/abc123',
        bool $branchException = false,
        ?string $compareExceptionMessage = null,
        array $pulls = [],
        bool $pullsException = false,
        array $compareData = ['commits' => [], 'files' => []],
        string $patchText = ''
    ): void {
        $this->replaceGitHubClient(new class(
            $branchCommitUrl,
            $branchException,
            $compareExceptionMessage,
            $pulls,
            $pullsException,
            $compareData,
            $patchText
        ) {
            public function __construct(
                private string $branchCommitUrl,
                private bool $branchException,
                private ?string $compareExceptionMessage,
                private array $pulls,
                private bool $pullsException,
                private array $compareData,
                private string $patchText
            ) {}

            public function api($endpoint) {
                return match ($endpoint) {
                    'repository' => new class($this->branchCommitUrl, $this->branchException) {
                        public function __construct(private string $branchCommitUrl, private bool $branchException) {}

                        public function branches($org, $repo, $branch) {
                            if ($this->branchException) {
                                throw new \Github\Exception\RuntimeException('Not Found');
                            }
                            return [
                                'name' => $branch,
                                'commit' => [
                                    'url' => $this->branchCommitUrl,
                                ],
                            ];
                        }
                    },
                    'repo' => new class($this->compareExceptionMessage, $this->pulls, $this->pullsException, $this->compareData, $this->patchText) {
                        public function __construct(
                            private ?string $compareExceptionMessage,
                            private array $pulls,
                            private bool $pullsException,
                            private array $compareData,
                            private string $patchText
                        ) {}

                        public function show($owner, $repo) {
                            return ['default_branch' => 'main'];
                        }

                        public function commits() {
                            return new class($this->compareExceptionMessage, $this->pulls, $this->pullsException, $this->compareData, $this->patchText) {
                                public function __construct(
                                    private ?string $compareExceptionMessage,
                                    private array $pulls,
                                    private bool $pullsException,
                                    private array $compareData,
                                    private string $patchText
                                ) {}

                                public function compare($org, $repo, $srcBranch, $repoBranch, $accept = null) {
                                    if ($this->compareExceptionMessage !== null) {
                                        throw new \Github\Exception\RuntimeException($this->compareExceptionMessage);
                                    }
                                    return $accept === null ? $this->compareData : $this->patchText;
                                }

                                public function pulls($org, $repo, $hash) {
                                    if ($this->pullsException) {
                                        throw new \Github\Exception\RuntimeException('temporary');
                                    }
                                    return $this->pulls;
                                }
                            };
                        }
                    },
                };
            }
        });
    }
}

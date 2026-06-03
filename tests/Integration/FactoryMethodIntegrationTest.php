<?php
// Integration test for Patch::factory using the upgraded MockGitHubClient

use PHPUnit\Framework\TestCase;
use Tests\Mocks\FakeQueryResultEntityManager;
use Tests\Mocks\MockGitHubClient;

require_once __DIR__ . '/../../entities/Patch.php';
require_once __DIR__ . '/../../entities/GitHub/GitHubPatch.php';
require_once __DIR__ . '/../../entities/ProjGroup.php';
require_once __DIR__ . '/../../entities/User.php';
require_once __DIR__ . '/../../entities/Shift.php';
require_once __DIR__ . '/../../entities/Repository.php';
require_once __DIR__ . '/../Mocks/MockGitHubClient.php';

class FactoryMethodIntegrationTest extends TestCase
{
    public function testPatchFactoryThrowsOnMergeCommitMessage()
    {
        [$group, $user, $url] = $this->patchFixture();
        $this->mockCompare(commits: [
            $this->commit(['message' => 'Merge branch feature']),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Merge commits are not allowed');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsOnMissingDcoSignature()
    {
        [$group, $user, $url] = $this->patchFixture();
        $group->dco = true;
        $this->mockCompare(commits: [
            $this->commit(['message' => 'A commit message without DCO']),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing signature in a project with DCO.');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsOnInvalidCoAuthoredByLine()
    {
        [$group, $user, $url] = $this->patchFixture();
        $this->mockCompare(commits: [
            $this->commit([
                'message' => "A commit\nCo-authored-by: someone <someone@example.com>\nnot at end",
            ]),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Co-authored-by lines must be at the end');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsOnShortCommitMessage()
    {
        [$group, $user, $url] = $this->patchFixture();
        $this->mockCompare(commits: [
            $this->commit(['message' => 'short']),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Commit message is too short');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsOnTooManyShortLines()
    {
        [$group, $user, $url] = $this->patchFixture();
        $this->mockCompare(commits: [
            $this->commit([
                'message' => "short\nshort\nshort\nshort\nshort\nshort\nshort\nshort\nshort\nshort\nlong enough line for limit",
            ]),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Most lines of the commit message are too short');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    private mixed $oldGitHubClient = null;
    private mixed $oldGitHubClientCached = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Save original globals before patching
        global $github_client, $github_client_cached;
        $this->oldGitHubClient = $github_client ?? null;
        $this->oldGitHubClientCached = $github_client_cached ?? null;
        // Set up the global mock client for all GitHub API calls
        $github_client = new MockGitHubClient();
        $github_client_cached = $github_client;
    }

    protected function tearDown(): void
    {
        // Restore original globals
        global $github_client, $github_client_cached;
        $github_client = $this->oldGitHubClient;
        $github_client_cached = $this->oldGitHubClientCached;
        parent::tearDown();
    }

    public function testPatchFactoryCreatesGitHubPatchSuccessfully()
    {
        [$group, $user, $url] = $this->patchFixture();
        $description = 'This is a test patch.';
        $this->mockCompare();

        $patch = Patch::factory($group, $url, PatchType::Feature, $description, $user);

        $this->assertInstanceOf(GitHub\GitHubPatch::class, $patch);
        $this->assertEquals($group, $patch->group);
        $this->assertEquals(PatchType::Feature, $patch->type);
        $this->assertEquals('feature-branch', $patch->branch());
        $this->assertEquals('mockorg:mockrepo:feature-branch', $patch->origin());
        $this->assertEquals('Mock User', $patch->commits()[0]['name']);
        $this->assertEquals('ist12345@tecnico.ulisboa.pt', $patch->commits()[0]['email']);
        $this->assertEquals('mocksha123', $patch->commits()[0]['hash']);
        $this->assertEquals(1, $patch->lines_added);
        $this->assertEquals(1, $patch->lines_deleted);
        $this->assertEquals(1, $patch->files_modified);
        $this->assertTrue($patch->isValid());
    }

    public function testPatchFactoryThrowsWhenNoRepository()
    {
        [$group, $user, $url] = $this->patchFixture(repository: '');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Group has no repository yet');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsOnEmptyDescription()
    {
        [$group, $user, $url] = $this->patchFixture();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Empty description');
        Patch::factory($group, $url, PatchType::Feature, '', $user);
    }

    public function testPatchFactoryThrowsOnNoRecognizedAuthors()
    {
        [$group, $user, $url] = $this->patchFixture();
        $group->resetStudents();
        $this->mockCompare(commits: [
            $this->commit(['message' => 'A valid commit message with no coauthors.']),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Name doesn't match any of the group's student names: Mock User");
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsOnForbiddenBranchName()
    {
        [$group, $user, $url] = $this->patchFixture(
            url: 'https://github.com/mockorg/mockrepo/compare/main...mockorg:mockrepo:main'
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid branch name: main');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsOnDuplicatePatch()
    {
        [$group, $user, $url] = $this->patchFixture();
        $this->mockCompare();

        $patch1 = Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
        $group->patches->add($patch1);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Duplicated patch');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsOnTrailingWhitespaceInDiff()
    {
        [$group, $user, $url] = $this->patchFixture();
        $this->mockCompare(files: [[
            'filename' => 'file1.php',
            'patch' => "+Added line   \n",
            'additions' => 1,
            'deletions' => 0,
        ]]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('has trailing whitespace');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsOnMissingNewlineInDiff()
    {
        [$group, $user, $url] = $this->patchFixture();
        $this->mockCompare(files: [[
            'filename' => 'file1.php',
            'patch' => "+Added line\n\\ No newline at end of file",
            'additions' => 1,
            'deletions' => 0,
        ]]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('is missing a newline at the end');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsOnInvalidCommitEmail()
    {
        [$group, $user, $url] = $this->patchFixture();
        $this->mockCompare(commits: [
            $this->commit(['email' => 'mockuser@example.com']),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid email used in commit');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsOnMalformedCoAuthoredByLine()
    {
        [$group, $user, $url] = $this->patchFixture();
        $this->mockCompare(commits: [
            $this->commit([
                'message' => 'A valid commit message with malformed coauthor marker.' .
                             "\nCo-authored by: Someone <someone@example.com>",
            ]),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid Co-authored-by line');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryCanIgnoreValidationErrors()
    {
        [$group, $user, $url] = $this->patchFixture();
        $this->mockCompare(files: [[
            'filename' => 'file1.php',
            'patch' => "+Added line   \n",
            'additions' => 1,
            'deletions' => 0,
        ]]);

        $patch = Patch::factory($group, $url, PatchType::Feature, 'desc', $user, '', true);

        $this->assertStringContainsString('Failed validation:', $patch->comments->last()->text);
        $this->assertStringContainsString('has trailing whitespace', $patch->comments->last()->text);
    }

    public function testPatchFactoryThrowsWhenFeatureIssueIsNotReferenced()
    {
        [$group, $user, $url] = $this->patchFixture(
            proposal: 'https://github.com/mockorg/mockrepo/issues/42'
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No commit message references the issue #id');
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryAllowsFeatureIssueWhenReferenced()
    {
        [$group, $user, $url] = $this->patchFixture(
            proposal: 'https://github.com/mockorg/mockrepo/issues/42'
        );
        $this->mockCompare(commits: [
            $this->commit([
                'message' => 'Implement feature requested in issue #42 with enough detail.',
            ]),
        ]);

        $patch = Patch::factory($group, $url, PatchType::Feature, 'desc', $user);

        $this->assertInstanceOf(GitHub\GitHubPatch::class, $patch);
    }

    public function testPatchFactoryThrowsWhenProjectDisallowsIssueRefs()
    {
        [$group, $user, $url] = $this->patchFixture(
            repository: 'github:godotengine/godot',
            url: 'https://github.com/godotengine/godot/compare/main...godotengine:godot:feature-branch'
        );
        $this->mockCompare(commits: [
            $this->commit([
                'message' => 'Fixes #42 with enough detail to pass validation.',
            ]),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Commit message references an issue, but it shouldn't");
        Patch::factory($group, $url, PatchType::Feature, 'desc', $user);
    }

    public function testPatchFactoryThrowsWhenBugFixHasMultipleCommits()
    {
        [$group, $user, $url] = $this->patchFixture();
        $oldEntityManager = $this->mockSelectedBug('https://github.com/mockorg/mockrepo/issues/42');
        $this->mockCompare(commits: [
            $this->commit(['message' => 'Fixes #42 with enough detail for first commit.']),
            $this->commit(['sha' => 'mocksha456', 'message' => 'Fixes #42 with enough detail for second commit.']),
        ]);

        try {
            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage('Only 1 commit allowed');
            Patch::factory($group, $url, PatchType::BugFix, 'desc', $user);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testPatchFactoryThrowsWhenBugFixHasNoSelectedBug()
    {
        [$group, $user, $url] = $this->patchFixture();
        $oldEntityManager = $this->mockSelectedBug(null);

        try {
            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage('Patch does not have a bug associated');
            Patch::factory($group, $url, PatchType::BugFix, 'desc', $user);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testPatchFactoryThrowsWhenBugFixDoesNotReferenceIssue()
    {
        [$group, $user, $url] = $this->patchFixture();
        $oldEntityManager = $this->mockSelectedBug('https://github.com/mockorg/mockrepo/issues/42');

        try {
            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage("Commit message doesn't reference the fixed issue properly");
            Patch::factory($group, $url, PatchType::BugFix, 'desc', $user);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testPatchFactoryThrowsWhenBugFixReferencesWrongIssue()
    {
        [$group, $user, $url] = $this->patchFixture();
        $oldEntityManager = $this->mockSelectedBug('https://github.com/mockorg/mockrepo/issues/42');
        $this->mockCompare(commits: [
            $this->commit([
                'message' => 'Fixes #99 with enough detail to pass message validation.',
            ]),
        ]);

        try {
            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage("Referenced issue #99 doesn't match");
            Patch::factory($group, $url, PatchType::BugFix, 'desc', $user);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testPatchFactoryCreatesBugFixWhenIssueIsReferenced()
    {
        [$group, $user, $url] = $this->patchFixture();
        $oldEntityManager = $this->mockSelectedBug('https://github.com/mockorg/mockrepo/issues/42');
        $this->mockCompare(commits: [
            $this->commit([
                'message' => 'Fixes #42 with enough detail to pass message validation.',
            ]),
        ]);

        try {
            $patch = Patch::factory($group, $url, PatchType::BugFix, 'desc', $user);
            $this->assertEquals(PatchType::BugFix, $patch->type);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    private function patchFixture(
        string $repository = 'github:mockorg/mockrepo',
        string $url = 'https://github.com/mockorg/mockrepo/compare/main...mockorg:mockrepo:feature-branch',
        string $proposal = ''
    ): array {
        $shift = new Shift('T01', 2026);
        $group = new ProjGroup(1, 2026, $shift);
        $group->repository = $repository;
        $group->url_proposal = $proposal;

        $user = new User('mockuser', 'Mock User', 'ist12345@tecnico.ulisboa.pt', '', 1, false);
        $group->addStudent($user);

        return [$group, $user, $url];
    }

    private function commit(array $overrides = []): array
    {
        $defaults = [
            'sha' => 'mocksha123',
            'login' => 'mockuser',
            'name' => 'Mock User',
            'email' => 'ist12345@tecnico.ulisboa.pt',
            'message' => 'Mock commit message with enough detail for testing.',
        ];
        $data = array_merge($defaults, $overrides);

        return [
            'sha' => $data['sha'],
            'author' => ['login' => $data['login']],
            'commit' => [
                'author' => ['name' => $data['name'], 'email' => $data['email']],
                'message' => $data['message'],
            ],
        ];
    }

    private function mockCompare(?array $commits = null, ?array $files = null): void
    {
        $commits ??= [$this->commit()];
        $files ??= [[
            'filename' => 'file1.php',
            'patch' => "+Added line\n-Removed line\n",
            'additions' => 1,
            'deletions' => 1,
        ]];

        global $github_client, $github_client_cached;
        $github_client = new class($commits, $files) extends \Tests\Mocks\MockGitHubClient {
            public function __construct(private array $commits, private array $files) {}

            public function api($endpoint) {
                if ($endpoint === 'issue') {
                    return new class {
                        public function show($owner, $repo, $number) {
                            return [
                                'title' => "Mock Issue #$number",
                                'body' => 'Mock issue body for patch review context.',
                            ];
                        }
                    };
                }
                if ($endpoint === 'repo') {
                    return new class($this->commits, $this->files) {
                        public function __construct(private array $commits, private array $files) {}

                        public function commits() {
                            return new class($this->commits, $this->files) {
                                public function __construct(private array $commits, private array $files) {}

                                public function compare($org, $repo, $srcBranch, $repoBranch, $accept = null) {
                                    if ($accept === 'application/vnd.github.patch') {
                                        return "diff --git a/file1.php b/file1.php\n+Added line\n-Removed line\n";
                                    }
                                    return [
                                        'commits' => $this->commits,
                                        'files' => $this->files,
                                    ];
                                }
                            };
                        }
                    };
                }
                return parent::api($endpoint);
            }
        };
        $github_client_cached = $github_client;
    }

    private function mockSelectedBug(?string $issueUrl): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $bug = null;
        if ($issueUrl !== null) {
            $bug = new SelectedBug();
            $bug->issue_url = $issueUrl;
        }

        $GLOBALS['entityManager'] = new FakeQueryResultEntityManager($bug);

        return $oldEntityManager;
    }
}

<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;


class PatchFactoryAndReviewIsolatedTest extends UnitTestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFactoryValidationBranchesAndReviewCommentSuccess()
    {
        $cwd = getcwd();
        $tmp = sys_get_temp_dir() . '/pic1_patch_review_' . bin2hex(random_bytes(4));
        mkdir($tmp);
        file_put_contents(
            $tmp . '/review.php',
            <<<'PHP'
<?php
function review_patch($project_name, $bug_fix, $patch, $patch_description, $issue_url, $issue_description, $coding_standard_url) {
    $GLOBALS['__review_arguments'] = [
        'issue_url' => $issue_url,
        'issue_description' => $issue_description,
    ];
    return 'Mock AI review';
}
PHP
        );

        try {
            chdir($tmp);
            require_once dirname(__DIR__, 2) . '/entities/Patch.php';
            $this->defineFakeGitHubPatch();

            $this->assertFactoryPropagatesUpdateException();
            $this->assertFactoryThrows('invalidAfterStats', 'Patch not found');
            $this->assertFactoryThrows('valid', 'Empty description', '');
            $this->assertFactoryThrows('noAuthors', 'Patch has no recognized authors');
            $this->assertFactoryThrows('mainBranch', 'Invalid branch name: main');
            $this->assertFactoryThrows('noCommits', 'No commit found in the given branch');
            $this->assertFactoryThrows(
                'valid',
                'Duplicated patch',
                'Patch description',
                static function (\ProjGroup $group): void {
                    $group->patches->add(new \GitHub\GitHubPatch());
                }
            );
            $this->assertFactoryThrows('trailingWhitespace', 'has trailing whitespace');
            $this->assertFactoryThrows('missingNewline', 'is missing a newline at the end');
            $this->assertFactoryThrows('invalidEmail', 'Invalid email used in commit');
            $this->assertFactoryThrows('mergeCommit', 'Merge commits are not allowed');
            $this->assertFactoryThrows(
                'valid',
                'Missing signature in a project with DCO.',
                'Patch description',
                static function (\ProjGroup $group): void {
                    $group->dco = true;
                }
            );
            $this->assertFactoryThrows('invalidCoauthor', 'Invalid Co-authored-by line');
            $this->assertFactoryThrows('coauthorNotAtEnd', 'Co-authored-by lines must be at the end');
            $this->assertFactoryThrows('shortMessage', 'Commit message is too short');
            $this->assertFactoryThrows('shortLines', 'Most lines of the commit message are too short');
            $this->assertFactoryThrows(
                'missingIssueReference',
                'No commit message references the issue #id',
                'Patch description',
                static function (\ProjGroup $group): void {
                    $group->url_proposal = 'https://github.com/mockorg/mockrepo/issues/42';
                }
            );

            \GitHub\GitHubPatch::$mode = 'trailingWhitespace';
            [$ignoredGroup, $ignoredSubmitter] = $this->factoryFixture();
            $ignoredPatch = \Patch::factory(
                $ignoredGroup,
                'https://github.com/mockorg/mockrepo/compare/main...mockorg:mockrepo:feature-branch',
                \PatchType::Feature,
                'Patch description',
                $ignoredSubmitter,
                '',
                true
            );
            $this->assertStringContainsString(
                'Failed validation:',
                $ignoredPatch->comments[1]->text
            );
            $this->assertStringContainsString(
                'has trailing whitespace',
                $ignoredPatch->comments[1]->text
            );

            \GitHub\GitHubPatch::$mode = 'valid';
            [$group, $submitter] = $this->factoryFixture();
            $group->url_proposal = 'https://github.com/mockorg/mockrepo/issues/42';
            $this->replaceCachedGitHubClient(new class {
                public function api($endpoint) {
                    return new class {
                        public function show($org, $repo, $number) {
                            return [
                                'title' => 'Stub issue title',
                                'body' => 'Stub issue description',
                            ];
                        }
                    };
                }
            });

            $patch = \Patch::factory(
                $group,
                'https://github.com/mockorg/mockrepo/compare/main...mockorg:mockrepo:feature-branch',
                \PatchType::Feature,
                "  Patch description  ",
                $submitter
            );

            $this->assertEquals('mockhash', $patch->hash);
            $this->assertCount(2, $patch->comments);
            $this->assertStringContainsString(
                "Patch submitted; hash: \n\nPatch description",
                $patch->comments->first()->text
            );
            $this->assertStringContainsString('Mock AI review', $patch->comments->last()->text);
            $this->assertStringContainsString('Commit: mockhash', $patch->comments->last()->text);
            $this->assertSame(
                'https://github.com/mockorg/mockrepo/issues/42',
                $GLOBALS['__review_arguments']['issue_url']
            );
            $this->assertSame(
                "Stub issue title\nStub issue description",
                $GLOBALS['__review_arguments']['issue_description']
            );
        } finally {
            chdir($cwd);
            @unlink($tmp . '/review.php');
            @rmdir($tmp);
        }
    }

    private function assertFactoryThrows(
        string $mode,
        string $message,
        string $description = 'Patch description',
        ?callable $configureGroup = null
    ): void {
        \GitHub\GitHubPatch::$mode = $mode;
        [$group, $submitter] = $this->factoryFixture();
        if ($configureGroup !== null) {
            $configureGroup($group);
        }

        try {
            \Patch::factory(
                $group,
                'https://github.com/mockorg/mockrepo/compare/main...mockorg:mockrepo:feature-branch',
                \PatchType::Feature,
                $description,
                $submitter
            );
            $this->fail("Factory mode $mode should throw");
        } catch (\ValidationException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    private function assertFactoryPropagatesUpdateException(): void
    {
        \GitHub\GitHubPatch::$mode = 'updateThrows';
        [$group, $submitter] = $this->factoryFixture();

        try {
            \Patch::factory(
                $group,
                'https://github.com/mockorg/mockrepo/compare/main...mockorg:mockrepo:feature-branch',
                \PatchType::Feature,
                'Patch description',
                $submitter
            );
            $this->fail('Factory should propagate an unexpected update failure');
        } catch (\Exception $exception) {
            $this->assertSame('temporary', $exception->getMessage());
        }
    }

    private function factoryFixture(): array
    {
        $shift = new \Shift('T01', 2026);
        $group = new \ProjGroup(1, 2026, $shift);
        $group->repository = 'github:mockorg/mockrepo';
        $group->project_name = 'Mock Project';

        $submitter = new \User(
            'mockuser',
            'Mock User',
            'ist12345@tecnico.ulisboa.pt',
            '',
            ROLE_STUDENT,
            false
        );
        $submitter->repository_user = 'github:mockuser';
        $group->addStudent($submitter);

        return [$group, $submitter];
    }

    private function defineFakeGitHubPatch(): void
    {
        if (class_exists('\\GitHub\\GitHubPatch', false)) {
            return;
        }

        eval(<<<'PHP'
namespace GitHub;

class GitHubPatch extends \Patch
{
    public static string $mode = 'valid';

    public static function construct($url, \Repository $repository)
    {
        return new self();
    }

    public function updateStats()
    {
        if (self::$mode === 'updateThrows') {
            throw new \Exception('temporary');
        }

        $this->hash = 'mockhash';
        $this->lines_added = 1;
        $this->lines_deleted = 1;
        $this->files_modified = 1;

        if (self::$mode === 'noAuthors') {
            $this->students = [];
        } elseif (!$this->group->students->isEmpty()) {
            $this->students->add($this->group->students->first());
        }
    }

    public function isValid(): bool
    {
        return self::$mode !== 'invalidAfterStats';
    }

    public function branch(): string
    {
        return self::$mode === 'mainBranch' ? 'main' : 'feature-branch';
    }

    public function origin(): string
    {
        return 'mockorg:mockrepo:feature-branch';
    }

    public function commits(): array
    {
        if (self::$mode === 'noCommits') {
            return [];
        }

        $email = self::$mode === 'invalidEmail'
            ? 'not-an-email'
            : 'ist12345@tecnico.ulisboa.pt';
        $message = match (self::$mode) {
            'mergeCommit' => 'Merge branch feature into main with enough explanatory detail.',
            'invalidCoauthor' => "Implement feature with complete detail.\nCo-authored by: Other <other@example.com>",
            'coauthorNotAtEnd' => "Implement feature with complete detail.\nCo-authored-by: Other <other@example.com>\nAdditional trailing explanation.",
            'shortMessage' => 'Too short',
            'shortLines' => "Tiny line\nTiny line\nTiny line\nA substantially longer explanation for this feature.",
            'missingIssueReference' => 'Implement feature with enough detail but without issue reference.',
            default => 'Implement feature for issue #42 with enough detail for testing.',
        };
        $coauthors = self::$mode === 'coauthorNotAtEnd'
            ? [['', 'Other', 'other@example.com']]
            : [];

        return [[
            'username' => 'mockuser',
            'name' => 'Mock User',
            'email' => $email,
            'message' => $message,
            'co-authored' => $coauthors,
        ]];
    }

    public function diff(): array
    {
        $patch = match (self::$mode) {
            'trailingWhitespace' => "+Added line \n",
            'missingNewline' => "+Added line\n\\ No newline at end of file",
            default => "+Added line\n-Removed line\n",
        };

        return [[
            'filename' => 'file1.php',
            'patch' => $patch,
        ]];
    }

    public function patch(): string
    {
        return "diff --git a/file1.php b/file1.php\n+Added line\n";
    }

    protected function computeBranchHash(): string
    {
        return 'mockhash';
    }

    protected function computeLinesAdded(): int
    {
        return 1;
    }

    protected function computeLinesDeleted(): int
    {
        return 1;
    }

    protected function computeFilesModified(): int
    {
        return 1;
    }

    public function getPatchURL(): string
    {
        return 'https://github.com/mockorg/mockrepo/compare/main...mockorg:mockrepo:feature-branch';
    }

    public function getCommitURL(string $hash): string
    {
        return "https://github.com/mockorg/mockrepo/commit/$hash";
    }

    public function setPR(\PullRequest $pr): void {}
    public function findAndSetPR(): bool { return false; }
    public function getPR(): ?\PullRequest { return null; }
}
PHP);
    }
}

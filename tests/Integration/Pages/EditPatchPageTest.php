<?php

namespace Tests\Integration\Pages;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Integration\Pages\PageRedirected;
use Tests\Mocks\FakePatch;
use Tests\Mocks\FakePullRequest;


require_once dirname(__DIR__, 3) . '/entities/Patch.php';

class EditPatchPageTest extends PageTestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEditPatchProfessorBuildsReviewControlsAndPatchDetails()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpEditPatchStubDirectory();
        $year = get_current_year();
        $prof = $this->createPageUser('ist9920', 'Professor Reviewer', ROLE_PROF);
        $prof->email = 'prof@example.com';
        $student = $this->createPageUser('ist9921', 'Alice Student', ROLE_STUDENT);
        $student->email = 'alice@example.com';

        $group = $this->createEditPatchGroup($year, $student, $prof);
        $group->hash_proposal_file = 'proposalhash';

        $patch = $this->createPatchStub(
            group: $group,
            submitter: $student,
            status: \PatchStatus::Reviewed,
            type: \PatchType::Feature,
            valid: true,
            pr: $this->createPullRequestStub('https://github.com/owner/repo/pull/12'),
            commits: [[
                'username' => 'alice',
                'name' => 'Alice Student',
                'email' => 'alice@example.com',
                'co-authored' => [],
            ]]
        );
        $patch->comments->add(new \PatchComment($patch, 'Automated review note', null));
        $patch->ci_failures->add(new \PatchCIError(
            $patch,
            'abc123',
            'lint',
            'https://ci.example/lint',
            new \DateTimeImmutable('2026-01-01 10:00:00')
        ));
        $patch->ci_failures->add(new \PatchCIError(
            $patch,
            'abc123',
            'tests',
            'https://ci.example/tests',
            new \DateTimeImmutable('2026-01-01 11:00:00')
        ));

        $oldEntityManager = $this->mockEditPatchEntityManager($patch, $year);

        try {
            $this->setUpPageRuntime('editpatch', $prof, 'GET', ['id' => $patch->id]);
            $this->runPage('editpatch');

            $this->assertNotNull($GLOBALS['comments_form']);
            $this->assertTrue($GLOBALS['comments_form']->has('approve'));
            $this->assertTrue($GLOBALS['comments_form']->has('reject'));

            $this->assertCount(2, $GLOBALS['comments']);
            $this->assertSame('Alice Student (ist9921)', $GLOBALS['comments'][0]['author']);
            $this->assertSame('', $GLOBALS['comments'][1]['author']);
            $this->assertStringContainsString('bottts', $GLOBALS['comments'][1]['photo']);

            $this->assertSame(
                'https://github.com/owner/repo/commit/abc123',
                $GLOBALS['ci_failures']['abc123']['url']
            );
            $this->assertSame(
                'https://ci.example/lint',
                $GLOBALS['ci_failures']['abc123']['failed']['lint']
            );
            $this->assertSame(
                'https://ci.example/tests',
                $GLOBALS['ci_failures']['abc123']['failed']['tests']
            );

            $this->assertCount(1, $GLOBALS['bottom_links']);
            $this->assertSame('Delete patch', $GLOBALS['bottom_links'][0]['label']);
            $this->assertSame(
                'index.php?id=9101&page=rmpatch',
                $GLOBALS['bottom_links'][0]['url']
            );

            $this->assertSame('Statistics', $GLOBALS['info_box']['title']);
            $this->assertSame(8, $GLOBALS['info_box']['rows']['Lines added']);
            $this->assertSame(2, $GLOBALS['info_box']['rows']['Lines removed']);
            $this->assertSame(1, $GLOBALS['info_box']['rows']['Files modified']);
            $this->assertSame(
                ['warn' => true, 'data' => 'Alice Student <alice@example.com>'],
                $GLOBALS['info_box']['rows']['All authors']
            );
            $this->assertSame(
                'https://github.com/owner/repo/tree/feature-branch',
                $GLOBALS['info_box']['rows']['Patch']['url']
            );
            $this->assertSame(
                'https://github.com/owner/repo/pull/12',
                $GLOBALS['info_box']['rows']['PR']['url']
            );
            $this->assertSame(
                'https://example.org/issues/51',
                $GLOBALS['info_box']['rows']['Issue']['url']
            );
            $this->assertSame(
                'index.php?download=5100&page=feature',
                $GLOBALS['embed_file']
            );

            $this->assertCount(1, $GLOBALS['__page_test_eval_boxes']);
            $this->assertSame('patch-feature', $GLOBALS['__page_test_eval_boxes'][0]['page']);
            $this->assertSame($student, $GLOBALS['__page_test_eval_boxes'][0]['student']);
            $this->assertSame($group, $GLOBALS['__page_test_eval_boxes'][0]['group']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
            $this->tearDownEditPatchStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEditPatchStudentCommentResubmitsReviewedPatchAndNotifiesTa()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpEditPatchStubDirectory();
        $year = get_current_year();
        $student = $this->createPageUser('ist9930', 'Alice Student', ROLE_STUDENT);
        $ta = $this->createPageUser('ist9931', 'Taylor Assistant', ROLE_TA);
        $ta->email = 'ta@example.com';

        $group = $this->createEditPatchGroup($year, $student, $ta);
        $patch = $this->createPatchStub(
            group: $group,
            submitter: $student,
            status: \PatchStatus::Reviewed
        );

        $oldEntityManager = $this->mockEditPatchEntityManager($patch, $year);
        $GLOBALS['patch'] = $patch;
        $GLOBALS['__editpatch_emails'] = [];

        try {
            $this->setUpPageRuntime(
                'editpatch',
                $student,
                'POST',
                ['id' => $patch->id],
                [
                    'comments' => [
                        'text' => 'I updated the patch after the review.',
                        'submit' => '',
                    ],
                ]
            );

            try {
                $this->runPage('editpatch');
                $this->fail('Expected page to redirect after submitting a comment.');
            } catch (PageRedirected) {
            }

            $latestComment = $patch->comments->last()->text;

            $this->assertSame('waiting review', $patch->getStatus());
            $this->assertCount(2, $patch->comments);
            $this->assertStringContainsString(
                'Status changed: reviewed (not approved) → waiting review',
                $latestComment
            );
            $this->assertStringContainsString(
                'I updated the patch after the review.',
                $latestComment
            );
            $this->assertSame('PIC1: new patch comment', $GLOBALS['__editpatch_emails'][0]['subject']);
            $this->assertSame('ta@example.com', $GLOBALS['__editpatch_emails'][0]['to']);
            $this->assertStringContainsString('Comment history:', $GLOBALS['__editpatch_emails'][0]['msg']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
            unset($GLOBALS['patch']);
            $this->tearDownEditPatchStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEditPatchTaApproveCommentApprovesPatchAndEmailsGroup()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpEditPatchStubDirectory();
        $year = get_current_year();
        $student = $this->createPageUser('ist9940', 'Alice Student', ROLE_STUDENT);
        $ta = $this->createPageUser('ist9941', 'Taylor Assistant', ROLE_TA);
        $ta->email = 'ta@example.com';

        $group = $this->createEditPatchGroup($year, $student, $ta);
        $patch = $this->createPatchStub(
            group: $group,
            submitter: $student,
            status: \PatchStatus::Reviewed
        );

        $oldEntityManager = $this->mockEditPatchEntityManager($patch, $year);
        $GLOBALS['patch'] = $patch;
        $GLOBALS['__editpatch_emails'] = [];

        try {
            $this->setUpPageRuntime(
                'editpatch',
                $ta,
                'POST',
                ['id' => $patch->id],
                [
                    'comments' => [
                        'text' => 'Looks good now.',
                        'approve' => '',
                    ],
                ]
            );

            try {
                $this->runPage('editpatch');
                $this->fail('Expected page to redirect after approving a patch.');
            } catch (PageRedirected) {
            }

            $latestComment = $patch->comments->last()->text;

            $this->assertSame('approved', $patch->getStatus());
            $this->assertStringContainsString(
                'Status changed: reviewed (not approved) → approved',
                $latestComment
            );
            $this->assertSame('PIC1: Patch approved', $GLOBALS['__editpatch_emails'][0]['subject']);
            $this->assertStringContainsString(
                'Congratulations! Your patch was approved. You can now open a PR.',
                $GLOBALS['__editpatch_emails'][0]['msg']
            );
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
            unset($GLOBALS['patch']);
            $this->tearDownEditPatchStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEditPatchTaRejectCommentMarksPatchReviewedAndEmailsGroup()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpEditPatchStubDirectory();
        $year = get_current_year();
        $student = $this->createPageUser('ist9950', 'Alice Student', ROLE_STUDENT);
        $ta = $this->createPageUser('ist9951', 'Taylor Assistant', ROLE_TA);
        $ta->email = 'ta@example.com';

        $group = $this->createEditPatchGroup($year, $student, $ta);
        $patch = $this->createPatchStub(
            group: $group,
            submitter: $student,
            status: \PatchStatus::WaitingReview
        );

        $oldEntityManager = $this->mockEditPatchEntityManager($patch, $year);
        $GLOBALS['patch'] = $patch;
        $GLOBALS['__editpatch_emails'] = [];

        try {
            $this->setUpPageRuntime(
                'editpatch',
                $ta,
                'POST',
                ['id' => $patch->id],
                [
                    'comments' => [
                        'text' => 'Please make one more update.',
                        'reject' => '',
                    ],
                ]
            );

            try {
                $this->runPage('editpatch');
                $this->fail('Expected page to redirect after rejecting a patch.');
            } catch (PageRedirected) {
            }

            $latestComment = $patch->comments->last()->text;

            $this->assertSame('reviewed (not approved)', $patch->getStatus());
            $this->assertStringContainsString(
                'Status changed: waiting review → reviewed (not approved)',
                $latestComment
            );
            $this->assertSame('PIC1: Patch reviewed', $GLOBALS['__editpatch_emails'][0]['subject']);
            $this->assertStringContainsString(
                'Your patch was reviewed, but it needs further changes.',
                $GLOBALS['__editpatch_emails'][0]['msg']
            );
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
            unset($GLOBALS['patch']);
            $this->tearDownEditPatchStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEditPatchTaPlainCommentSendsGenericGroupNotification()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpEditPatchStubDirectory();
        $year = get_current_year();
        $student = $this->createPageUser('ist9960', 'Alice Student', ROLE_STUDENT);
        $ta = $this->createPageUser('ist9961', 'Taylor Assistant', ROLE_TA);
        $ta->email = 'ta@example.com';

        $group = $this->createEditPatchGroup($year, $student, $ta);
        $patch = $this->createPatchStub(
            group: $group,
            submitter: $student,
            status: \PatchStatus::Reviewed
        );

        $oldEntityManager = $this->mockEditPatchEntityManager($patch, $year);
        $GLOBALS['patch'] = $patch;
        $GLOBALS['__editpatch_emails'] = [];

        try {
            $this->setUpPageRuntime(
                'editpatch',
                $ta,
                'POST',
                ['id' => $patch->id],
                [
                    'comments' => [
                        'text' => 'Please check the CI output.',
                        'submit' => '',
                    ],
                ]
            );

            try {
                $this->runPage('editpatch');
                $this->fail('Expected page to redirect after adding a comment.');
            } catch (PageRedirected) {
            }

            $this->assertSame('reviewed (not approved)', $patch->getStatus());
            $this->assertSame('PIC1: new patch comment', $GLOBALS['__editpatch_emails'][0]['subject']);
            $this->assertStringContainsString(
                'Please check the CI output.',
                $GLOBALS['__editpatch_emails'][0]['msg']
            );
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
            unset($GLOBALS['patch']);
            $this->tearDownEditPatchStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEditPatchFormUpdateAddsVideoChangeComment()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpEditPatchStubDirectory();
        $year = get_current_year();
        $student = $this->createPageUser('ist9970', 'Alice Student', ROLE_STUDENT);
        $prof = $this->createPageUser('ist9971', 'Professor Reviewer', ROLE_PROF);

        $group = $this->createEditPatchGroup($year, $student, $prof);
        $patch = $this->createPatchStub(
            group: $group,
            submitter: $student,
            status: \PatchStatus::Reviewed,
            videoUrl: 'https://example.org/old-video'
        );

        $oldEntityManager = $this->mockEditPatchEntityManager($patch, $year);

        try {
            $this->setUpPageRuntime(
                'editpatch',
                $student,
                'POST',
                ['id' => $patch->id],
                [
                    'form' => [
                        'type' => (string)\PatchType::Feature->value,
                        'video_url' => '',
                        'submit' => '',
                    ],
                ]
            );
            $this->runPage('editpatch');

            $this->assertSame('', $patch->video_url);
            $this->assertSame('Database updated!', $GLOBALS['success_message']);
            $this->assertCount(2, $patch->comments);
            $this->assertSame(
                'Video URL changed: https://example.org/old-video → ',
                $patch->comments->last()->text
            );
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
            $this->tearDownEditPatchStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEditPatchShowsUnavailableVideoMessageForInvalidStoredVideoUrl()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpEditPatchStubDirectory();
        $year = get_current_year();
        $prof = $this->createPageUser('ist9980', 'Professor Reviewer', ROLE_PROF);
        $student = $this->createPageUser('ist9981', 'Alice Student', ROLE_STUDENT);

        $group = $this->createEditPatchGroup($year, $student, $prof);
        $patch = $this->createPatchStub(
            group: $group,
            submitter: $student,
            videoUrl: 'https://example.invalid/video'
        );

        $oldEntityManager = $this->mockEditPatchEntityManager($patch, $year);

        try {
            $this->setUpPageRuntime('editpatch', $prof, 'GET', ['id' => $patch->id]);
            $this->runPage('editpatch');

            $this->assertSame('Video no longer available', $GLOBALS['info_message']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
            $this->tearDownEditPatchStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEditPatchDisplaysUnavailablePatchInfoWhenPatchIsNoLongerValid()
    {
        [$oldWorkingDirectory, $stubDir] = $this->setUpEditPatchStubDirectory();
        $year = get_current_year();
        $prof = $this->createPageUser('ist9990', 'Professor Reviewer', ROLE_PROF);
        $student = $this->createPageUser('ist9991', 'Alice Student', ROLE_STUDENT);

        $group = $this->createEditPatchGroup($year, $student, $prof);
        $patch = $this->createPatchStub(
            group: $group,
            submitter: $student,
            valid: false
        );

        $oldEntityManager = $this->mockEditPatchEntityManager($patch, $year);

        try {
            $this->setUpPageRuntime('editpatch', $prof, 'GET', ['id' => $patch->id]);
            $this->runPage('editpatch');

            $this->assertSame('The patch is no longer available!', $GLOBALS['info_box']['title']);
            $this->assertSame(
                'https://github.com/owner/repo/tree/feature-branch',
                $GLOBALS['info_box']['rows']['Patch']['url']
            );
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
            $this->tearDownEditPatchStubDirectory($oldWorkingDirectory, $stubDir);
        }
    }

    private function setUpEditPatchStubDirectory(): array
    {
        $oldWorkingDirectory = getcwd();
        $stubDir = sys_get_temp_dir() . '/pic1_editpatch_stubs_' . bin2hex(random_bytes(4));
        mkdir($stubDir);

        file_put_contents(
            $stubDir . '/email.php',
            <<<'PHP'
<?php
function email_ta($group, $subject, $msg, $html = false) {
    $GLOBALS['__editpatch_emails'][] = [
        'to' => $group->shift->prof?->email ?? '',
        'subject' => $subject,
        'msg' => $msg,
        'html' => $html,
    ];
}
function email_group($group, $subject, $msg, $html = false) {
    $GLOBALS['__editpatch_emails'][] = [
        'to' => $group->shift->prof?->email ?? '',
        'subject' => $subject,
        'msg' => $msg,
        'html' => $html,
    ];
}
PHP
        );
        file_put_contents(
            $stubDir . '/review.php',
            <<<'PHP'
<?php
function review_patch($project_name, $bug_fix, $patch, $patch_description, $issue_url, $issue_description, $coding_standard_url) {
    return 'Stub AI review';
}
PHP
        );

        chdir($stubDir);

        return [$oldWorkingDirectory, $stubDir];
    }

    private function tearDownEditPatchStubDirectory(string|false $oldWorkingDirectory, string $stubDir): void
    {
        if ($oldWorkingDirectory !== false) {
            chdir($oldWorkingDirectory);
        }
        @unlink($stubDir . '/email.php');
        @unlink($stubDir . '/review.php');
        @rmdir($stubDir);
    }

    private function createEditPatchGroup(int $year, \User $student, \User $reviewer): \ProjGroup
    {
        $group = $this->createPageGroup(
            51,
            $year,
            [$student],
            prof: $reviewer,
            groupId: 5100,
            repository: 'github:owner/repo'
        );
        $group->url_proposal = 'https://example.org/issues/51';

        return $group;
    }

    private function createPatchStub(
        \ProjGroup $group,
        \User $submitter,
        \PatchStatus $status = \PatchStatus::Reviewed,
        \PatchType $type = \PatchType::Feature,
        bool $valid = true,
        ?\PullRequest $pr = null,
        string $patchUrl = 'https://github.com/owner/repo/tree/feature-branch',
        string $videoUrl = '',
        array $commits = [[
            'username' => 'alice',
            'name' => 'Alice Student',
            'email' => 'alice@example.com',
            'co-authored' => [],
        ]]
    ): \Patch {
        $patch = new FakePatch(
            valid: $valid,
            patchOrigin: 'alice:repo:feature-branch',
            patchCommits: $commits,
            patchText: "diff --git a/file.php b/file.php\n",
            branchHash: 'hash',
            computedLinesAdded: 8,
            computedLinesDeleted: 2,
            computedFilesModified: 1,
            patchUrl: $patchUrl,
            commitUrlBase: 'https://github.com/owner/repo/commit/',
            pullRequest: $pr
        );

        $patch->id = 9101;
        $patch->group = $group;
        $patch->status = $status;
        $patch->type = $type;
        $patch->video_url = $videoUrl;
        $patch->hash = 'abc123';
        $patch->lines_added = 8;
        $patch->lines_deleted = 2;
        $patch->files_modified = 1;
        $patch->students->add($submitter);
        $patch->comments->add(new \PatchComment($patch, 'Initial patch submission', $submitter));

        return $patch;
    }

    private function createPullRequestStub(string $url): \PullRequest
    {
        return new FakePullRequest(
            pullRequestUrl: $url,
            branchUrl: 'https://github.com/owner/repo/tree/feature-branch',
            pullRequestOrigin: 'alice:repo:feature-branch',
            merger: 'reviewer',
            mergedAt: new \DateTimeImmutable('2026-01-01 12:00:00'),
            addedLines: 8,
            deletedLines: 2,
            modifiedFiles: 1
        );
    }

    private function mockEditPatchEntityManager(\Patch $patch, int $year): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($patch, $year) {
            public int $flushCount = 0;

            public function __construct(private \Patch $patch, private int $year) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'Patch' && (int)$id === $this->patch->id) {
                    return $this->patch;
                }
                if ($entity === 'Deadline') {
                    $deadline = new \Deadline($this->year);
                    $deadline->patch_submission = new \DateTimeImmutable('+1 day');
                    return $deadline;
                }

                return null;
            }

            public function flush(): void
            {
                $this->flushCount++;
            }
        };

        return $oldEntityManager;
    }
}

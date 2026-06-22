<?php

namespace Tests\Integration\Pages;

use Tests\Mocks\FakePatch;
use Tests\Mocks\FakePullRequest;
require_once dirname(__DIR__, 3) . '/entities/Patch.php';

class PatchesPageTest extends PageTestCase
{
    public function testPatchesPageTerminatesWhenSubmittingWithoutGroup()
    {
        $year = get_current_year();
        $oldEntityManager = $this->mockPatchesEntityManager($this->openDeadline($year));

        try {
            $student = $this->createPageUser('ist9900', 'No Group Student', ROLE_STUDENT);
            $student->repository_user = 'github:nogroup';

            $this->setUpPageRuntime(
                'patches',
                $student,
                'POST',
                [],
                ['form' => $this->submissionFormData()]
            );

            $this->expectException(PageTerminated::class);
            $this->expectExceptionMessage('Student is not in a group');
            $this->runPage('patches');
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testPatchesPageTerminatesWhenStudentHasNoRepositoryUser()
    {
        $year = get_current_year();
        $oldEntityManager = $this->mockPatchesEntityManager($this->openDeadline($year));

        try {
            $student = $this->createPageUser('ist9901', 'No Repo User', ROLE_STUDENT);
            $student->email = 'ist9901@tecnico.ulisboa.pt';
            $this->createGroup(41, $year, [$student]);

            $this->setUpPageRuntime(
                'patches',
                $student,
                'POST',
                [],
                ['form' => $this->submissionFormData()]
            );

            $this->expectException(PageTerminated::class);
            $this->expectExceptionMessage('Student does not have an associated repository user');
            $this->runPage('patches');
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testPatchesPageBuildsStudentFormAndPatchTable()
    {
        $year = get_current_year();
        $oldEntityManager = $this->mockPatchesEntityManager($this->openDeadline($year));

        try {
            $student = $this->createPageUser('ist9902', 'Alice Student', ROLE_STUDENT);
            $group = $this->createGroup(42, $year, [$student]);
            $group->url_proposal = 'https://example.org/issues/42';

            $patch = $this->createPatchStub(
                id: 701,
                group: $group,
                submitter: $student,
                authors: [$student],
                status: \PatchStatus::WaitingReview,
                type: \PatchType::Feature,
                patchUrl: 'https://github.com/owner/repo/tree/feature-branch',
                prUrl: null,
                linesAdded: 14,
                linesDeleted: 3,
                filesModified: 2
            );
            $group->patches->add($patch);

            $this->setUpPageRuntime('patches', $student);
            $this->runPage('patches');

            $this->assertNotNull($GLOBALS['form']);
            $this->assertTrue($GLOBALS['form']->has('url'));
            $this->assertTrue($GLOBALS['form']->has('type'));
            $this->assertTrue($GLOBALS['form']->has('video_url'));
            $this->assertTrue($GLOBALS['form']->has('description'));
            $this->assertTrue($GLOBALS['form']->has('submit'));

            $this->assertCount(1, $GLOBALS['table']);
            $row = $GLOBALS['table'][0];
            $this->assertSame(701, $row['id']['label']);
            $this->assertSame('index.php?id=701&page=editpatch', $row['id']['url']);
            $this->assertSame(42, $row['Group']['label']);
            $this->assertSame('index.php?id=' . $group->id . '&page=listproject', $row['Group']['url']);
            $this->assertSame('waiting review', $row['Status']);
            $this->assertSame('feature', $row['Type']);
            $this->assertSame('https://example.org/issues/42', $row['Issue']['url']);
            $this->assertSame('https://github.com/owner/repo/tree/feature-branch', $row['Patch']['url']);
            $this->assertSame('', $row['PR']);
            $this->assertSame(14, $row['+']);
            $this->assertSame(3, $row['-']);
            $this->assertSame(2, $row['Files']);
            $this->assertSame('Alice Student', $row['Submitter']);
            $this->assertSame('Alice Student', $row['Authors']);
            $this->assertTrue($row['_large_table']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testPatchesPageDoesNotBuildStudentFormAfterDeadline()
    {
        $year = get_current_year();
        $oldEntityManager = $this->mockPatchesEntityManager($this->closedDeadline($year));

        try {
            $student = $this->createPageUser('ist9906', 'Late Student', ROLE_STUDENT);
            $group = $this->createGroup(46, $year, [$student]);
            $patch = $this->createPatchStub(
                id: 704,
                group: $group,
                submitter: $student,
                authors: [$student],
                status: \PatchStatus::WaitingReview,
                type: \PatchType::Feature,
                patchUrl: 'https://github.com/owner/repo/tree/late-branch',
                prUrl: null,
                linesAdded: 12,
                linesDeleted: 1,
                filesModified: 1
            );
            $group->patches->add($patch);

            $this->setUpPageRuntime('patches', $student);
            $this->runPage('patches');

            $this->assertNull($GLOBALS['form']);
            $this->assertCount(1, $GLOBALS['table']);
            $this->assertSame(704, $GLOBALS['table'][0]['id']['label']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testPatchesPageFiltersTaViewByReviewAndOpenStateWithoutSubmissionForm()
    {
        $year = get_current_year();
        $oldEntityManager = $this->mockPatchesEntityManager($this->openDeadline($year));

        try {
            $ta = $this->createPageUser('ist9903', 'Taylor Assistant', ROLE_TA);

            $studentA = $this->createPageUser('ist9904', 'Alice Student', ROLE_STUDENT);
            $groupA = $this->createGroup(43, $year, [$studentA]);
            $groupA->url_proposal = 'https://example.org/issues/43';
            $waitingReview = $this->createPatchStub(
                id: 702,
                group: $groupA,
                submitter: $studentA,
                authors: [$studentA],
                status: \PatchStatus::WaitingReview,
                type: \PatchType::Feature,
                patchUrl: 'https://github.com/owner/repo/tree/review-branch',
                prUrl: 'https://github.com/owner/repo/pull/12',
                linesAdded: 20,
                linesDeleted: 4,
                filesModified: 3
            );
            $groupA->patches->add($waitingReview);

            $studentB = $this->createPageUser('ist9905', 'Bob Student', ROLE_STUDENT);
            $groupB = $this->createGroup(44, $year, [$studentB]);
            $groupB->url_proposal = 'https://example.org/issues/44';
            $merged = $this->createPatchStub(
                id: 703,
                group: $groupB,
                submitter: $studentB,
                authors: [$studentB],
                status: \PatchStatus::Merged,
                type: \PatchType::Feature,
                patchUrl: 'https://github.com/owner/repo/tree/merged-branch',
                prUrl: 'https://github.com/owner/repo/pull/13',
                linesAdded: 30,
                linesDeleted: 5,
                filesModified: 4
            );
            $groupB->patches->add($merged);

            $GLOBALS['__page_test_filter_result'] = [[$groupA, $groupB], true, true];

            $this->setUpPageRuntime('patches', $ta);
            $this->runPage('patches');

            $this->assertNull($GLOBALS['form']);
            $this->assertCount(1, $GLOBALS['table']);
            $row = $GLOBALS['table'][0];
            $this->assertSame(702, $row['id']['label']);
            $this->assertSame('waiting review', $row['Status']);
            $this->assertSame('https://github.com/owner/repo/pull/12', $row['PR']['url']);
            $this->assertSame('Alice Student', $row['Authors']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testPatchesPageSubmitsPatchSuccessfully()
    {
        $result = $this->runPatchesSubmissionScript(duplicate: false);

        $this->assertSame(0, $result['exit_code']);
        $data = json_decode($result['output'], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Patch submitted successfully!', $data['success_message']);
        $this->assertSame(1, $data['patch_count']);
        $this->assertSame(1, $data['saved_count']);
        $this->assertSame('feature', $data['saved_patch_type']);
        $this->assertSame('https://github.com/owner/repo/compare/alice%3Arepo%3Afeature-branch', $data['saved_patch_url']);
        $this->assertSame('Stub AI review', $data['review_text']);
        $this->assertSame('PIC1: New patch', $data['email_subject']);
        $this->assertSame('ta@example.com', $data['email_to']);
    }

    public function testPatchesPageShowsFriendlyMessageForDuplicateBranch()
    {
        $result = $this->runPatchesSubmissionScript(duplicate: true);

        $this->assertSame(0, $result['exit_code']);
        $data = json_decode($result['output'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(
            'A patch with the same branch already exists. Please update the existing patch instead of creating a new one.',
            $data['error_message']
        );
    }

    private function createGroup(int $number, int $year, array $students): \ProjGroup
    {
        return $this->createPageGroup(
            $number,
            $year,
            $students,
            groupId: $number * 100,
            shiftId: $number * 10,
            repository: 'github:owner/repo'
        );
    }

    private function createPatchStub(
        int $id,
        \ProjGroup $group,
        \User $submitter,
        array $authors,
        \PatchStatus $status,
        \PatchType $type,
        string $patchUrl,
        ?string $prUrl,
        int $linesAdded,
        int $linesDeleted,
        int $filesModified
    ): \Patch {
        $pr = $prUrl === null ? null : new FakePullRequest(
            pullRequestUrl: $prUrl,
            branchUrl: 'https://github.com/owner/repo/tree/feature-branch',
            pullRequestOrigin: 'alice:repo:feature-branch',
            addedLines: 20,
            deletedLines: 4,
            modifiedFiles: 3
        );
        $patch = new FakePatch(
            patchOrigin: 'alice:repo:feature-branch',
            patchText: "diff --git a/file.php b/file.php\n",
            branchHash: 'mockhash',
            computedLinesAdded: 0,
            computedLinesDeleted: 0,
            computedFilesModified: 0,
            patchUrl: $patchUrl,
            commitUrlBase: 'https://github.com/owner/repo/commit/',
            pullRequest: $pr
        );

        if ($id > 0) {
            $patch->id = $id;
        }
        $patch->group = $group;
        $patch->status = $status;
        $patch->type = $type;
        $patch->lines_added = $linesAdded;
        $patch->lines_deleted = $linesDeleted;
        $patch->files_modified = $filesModified;
        $patch->comments->add(new \PatchComment($patch, 'Patch submitted', $submitter));
        foreach ($authors as $author) {
            $patch->students->add($author);
        }

        return $patch;
    }

    private function mockPatchesEntityManager(\Deadline $deadline): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($deadline) {
            public function __construct(private \Deadline $deadline) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'Deadline') {
                    return $this->deadline;
                }

                return null;
            }

            public function persist($obj): void
            {
            }

            public function flush(): void
            {
            }
        };

        return $oldEntityManager;
    }

    private function submissionFormData(
        string $url = 'https://github.com/owner/repo/tree/feature-branch',
        string $description = 'This patch implements a valid feature description.'
    ): array {
        return [
            'url' => $url,
            'type' => '1',
            'video_url' => '',
            'description' => $description,
            'submit' => '',
        ];
    }

    private function openDeadline(int $year): \Deadline
    {
        $deadline = new \Deadline($year);
        $deadline->patch_submission = new \DateTimeImmutable('+1 day');
        return $deadline;
    }

    private function closedDeadline(int $year): \Deadline
    {
        $deadline = new \Deadline($year);
        $deadline->patch_submission = new \DateTimeImmutable('-1 day');
        return $deadline;
    }

    private function runPatchesSubmissionScript(bool $duplicate): array
    {
        $script = sys_get_temp_dir() . '/pic1_patches_' . bin2hex(random_bytes(4)) . '.php';
        $bootstrap = var_export(dirname(__DIR__, 3) . '/tests/bootstrap.php', true);
        $pageTestCase = var_export(dirname(__DIR__) . '/Pages/PageTestCase.php', true);
        $page = var_export(dirname(__DIR__, 3) . '/pages/patches.php', true);
        $patchEntity = var_export(dirname(__DIR__, 3) . '/entities/Patch.php', true);
        $stubDir = sys_get_temp_dir() . '/pic1_patch_stubs_' . bin2hex(random_bytes(4));
        $duplicateFlag = $duplicate ? 'true' : 'false';

        mkdir($stubDir);
        file_put_contents(
            $stubDir . '/email.php',
            <<<'PHP'
<?php
function email_ta($group, $subject, $msg, $html = false) {
    $GLOBALS['__patches_email'][] = [
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

        $code = <<<PHP
<?php
require $bootstrap;
require $pageTestCase;
chdir('$stubDir');
require_once $patchEntity;
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_GET = ['page' => 'patches'];
\$_POST = [
    'form' => [
        'url' => 'https://github.com/owner/repo/tree/feature-branch',
        'type' => '1',
        'video_url' => '',
        'description' => 'This patch implements a complete feature for testing.',
        'submit' => '',
    ],
];
\$_REQUEST = \$_GET + \$_POST;
\$request = Symfony\Component\HttpFoundation\Request::create('/index.php?page=patches', 'POST', \$_POST);
\$formFactory = Symfony\Component\Form\Forms::createFormFactoryBuilder()
    ->addExtension(new Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension())
    ->getFormFactory();
\$GLOBALS['page'] = 'patches';
\$GLOBALS['__patches_email'] = [];
\$year = get_current_year();
\$student = new User('ist9910', 'Alice Student', 'ist9910@tecnico.ulisboa.pt', '', ROLE_STUDENT, false);
\$student->repository_user = 'github:alice';
\$ta = new User('ist9911', 'TA Reviewer', 'ta@example.com', '', ROLE_TA, false);
\$shift = new Shift('T1', \$year);
\$shift->prof = \$ta;
\$group = new ProjGroup(45, \$year, \$shift);
\$group->id = 4500;
\$group->repository = 'github:owner/repo';
\$group->project_name = 'Patch Project';
\$group->addStudent(\$student);
\$GLOBALS['__page_test_user'] = \$student;
\$GLOBALS['github_client'] = new class {
    public function api(\$endpoint) {
        if (\$endpoint === 'user') {
            return new class {
                public function show(\$username) {
                    return ['login' => \$username, 'name' => 'Alice Student', 'email' => 'ist9910@tecnico.ulisboa.pt'];
                }
            };
        }
        if (\$endpoint === 'repository') {
            return new class {
                public function branches(\$org, \$repo, \$branch) {
                    return [
                        'name' => \$branch,
                        'commit' => ['url' => "https://api.github.com/repos/alice/repo/commits/mocksha123"],
                    ];
                }
            };
        }
        if (\$endpoint === 'repo') {
            return new class {
                public function show(\$owner, \$repo) {
                    return [
                        'full_name' => "\$owner/\$repo",
                        'default_branch' => 'main',
                        'language' => 'PHP',
                        'license' => ['name' => 'MIT'],
                        'stargazers_count' => 100,
                        'topics' => ['testing'],
                    ];
                }
                public function commits() {
                    return new class {
                        public function compare(\$org, \$repo, \$srcBranch, \$repoBranch, \$accept = null) {
                            if (\$accept === 'application/vnd.github.patch') {
                                return "diff --git a/src.php b/src.php\n+new line\n";
                            }
                            return [
                                'commits' => [[
                                    'sha' => 'mocksha123',
                                    'author' => ['login' => 'alice'],
                                    'commit' => [
                                        'author' => ['name' => 'Alice Student', 'email' => 'ist9910@tecnico.ulisboa.pt'],
                                        'message' => "Implement feature branch support with enough detail.\n\nTechnical explanation of the implementation steps.",
                                    ],
                                ]],
                                'files' => [[
                                    'filename' => 'src.php',
                                    'patch' => "+new line\n",
                                    'additions' => 1,
                                    'deletions' => 0,
                                ]],
                            ];
                        }
                    };
                }
            };
        }
        throw new RuntimeException('Unexpected endpoint: ' . \$endpoint);
    }
};
\$GLOBALS['github_client_cached'] = \$GLOBALS['github_client'];
\$GLOBALS['entityManager'] = new class(\$group, \$year, $duplicateFlag) {
    public array \$saved = [];

    public function __construct(
        private ProjGroup \$group,
        private int \$year,
        private bool \$duplicate
    ) {}

    public function find(string \$entity, \$id): mixed
    {
        if (\$entity === 'Deadline') {
            \$deadline = new Deadline(\$this->year);
            \$deadline->patch_submission = new DateTimeImmutable('+1 day');
            return \$deadline;
        }

        return null;
    }

    public function persist(\$obj): void
    {
        if (\$obj instanceof Patch && !isset(\$obj->id)) {
            \$obj->id = 8801;
        }
        \$this->saved[] = \$obj;
    }

    public function flush(): void
    {
        if (!\$this->duplicate) {
            return;
        }

        \$driverException = new class('duplicate branch', 19) extends RuntimeException implements Doctrine\DBAL\Driver\Exception {
            public function getSQLState(): ?string
            {
                return '23000';
            }
        };

        throw new Doctrine\DBAL\Exception\UniqueConstraintViolationException(\$driverException, null);
    }
};
try {
    require $page;
    \$savedPatch = \$GLOBALS['entityManager']->saved[0] ?? null;
    \$reviewText = null;
    if (\$savedPatch) {
        foreach (\$savedPatch->comments as \$comment) {
            if (str_contains(\$comment->text, 'Stub AI review')) {
                \$reviewText = 'Stub AI review';
                break;
            }
        }
    }
    echo json_encode([
        'success_message' => \$success_message ?? null,
        'error_message' => null,
        'patch_count' => count(\$group->patches),
        'saved_count' => count(\$GLOBALS['entityManager']->saved),
        'saved_patch_type' => \$savedPatch?->getType(),
        'saved_patch_url' => \$savedPatch?->getPatchURL(),
        'review_text' => \$reviewText,
        'email_subject' => \$GLOBALS['__patches_email'][0]['subject'] ?? null,
        'email_to' => \$GLOBALS['__patches_email'][0]['to'] ?? null,
    ]);
} catch (Tests\Integration\Pages\PageTerminated \$terminated) {
    echo json_encode([
        'success_message' => null,
        'error_message' => \$terminated->getMessage(),
    ]);
}
PHP;

        file_put_contents($script, $code);

        try {
            $output = [];
            $exitCode = 0;
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script), $output, $exitCode);

            return [
                'output' => implode("\n", $output),
                'exit_code' => $exitCode,
            ];
        } finally {
            @unlink($script);
            @unlink($stubDir . '/email.php');
            @unlink($stubDir . '/review.php');
            @rmdir($stubDir);
        }
    }
}

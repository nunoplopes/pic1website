<?php

namespace Tests\Integration\Pages;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;



class ReportPageTest extends PageTestCase
{
    public function testReportPageTerminatesWhenStudentHasNoGroup()
    {
        $year = get_current_year();
        $deadline = new \Deadline($year);
        $deadline->final_report = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockReportEntityManager($deadline);

        try {
            $this->setUpPageRuntime(
                'report',
                $this->createPageUser('ist9800', 'No Group Student', ROLE_STUDENT)
            );

            $this->expectException(PageTerminated::class);
            $this->expectExceptionMessage('Student is not in a group');
            $this->runPage('report');
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testReportPageBuildsStudentUploadFormTableAndEvalBox()
    {
        $year = get_current_year();
        $student = $this->createPageUser('ist9801', 'Report Student', ROLE_STUDENT);
        $group = $this->createGroup(31, $year, [$student]);
        $group->hash_final_report = 'reporthash31';
        $group->allow_modifications_date = new \DateTimeImmutable('+2 days');

        $deadline = new \Deadline($year);
        $deadline->final_report = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockReportEntityManager($deadline);

        try {
            $this->setUpPageRuntime('report', $student);
            $this->runPage('report');

            $this->assertSame(
                'You can submit this form multiple times until the deadline. Only the last submission will be considered.',
                $GLOBALS['info_message']
            );
            $this->assertNotNull($GLOBALS['form']);
            $this->assertTrue($GLOBALS['form']->has('file'));
            $this->assertTrue($GLOBALS['form']->has('submit'));

            $this->assertSame(
                [
                    [
                        'Group' => 31,
                        'PDF' => [
                            'label' => 'link',
                            'url' => 'index.php?download=' . $group->id . '&page=report',
                        ],
                    ],
                ],
                $GLOBALS['table']
            );
            $this->assertSame(
                'index.php?download=' . $group->id . '&page=report',
                $GLOBALS['embed_file']
            );
            $this->assertCount(1, $GLOBALS['__page_test_eval_boxes']);
            $this->assertSame('report', $GLOBALS['__page_test_eval_boxes'][0]['page']);
            $this->assertSame($group, $GLOBALS['__page_test_eval_boxes'][0]['group']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testReportPageUploadsPdfAndStoresFinalReportHash()
    {
        $year = get_current_year();
        $student = $this->createPageUser('ist9802', 'Upload Student', ROLE_STUDENT);
        $group = $this->createGroup(32, $year, [$student]);
        $group->allow_modifications_date = new \DateTimeImmutable('+2 days');

        $deadline = new \Deadline($year);
        $deadline->final_report = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockReportEntityManager($deadline);
        [$file, $tmpPath, $expectedHash] = $this->createUploadedPdf();
        $uploadedPath = $this->uploadPath($expectedHash);

        try {
            $this->setUpPageRuntime(
                'report',
                $student,
                'POST',
                [],
                [
                    'form' => [
                        'submit' => '',
                    ],
                ]
            );

            global $request;
            $request = Request::create(
                '/index.php?page=report',
                'POST',
                $_POST,
                [],
                ['form' => ['file' => $file]]
            );

            $this->runPage('report');

            $this->assertSame('File uploaded successfully!', $GLOBALS['success_message']);
            $this->assertSame($expectedHash, $group->hash_final_report);
            $this->assertFileExists($uploadedPath);
            $this->assertSame(
                'index.php?download=' . $group->id . '&page=report',
                $GLOBALS['embed_file']
            );
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
            if (file_exists($uploadedPath)) {
                @unlink($uploadedPath);
            }
        }
    }

    public function testReportPageUsesFilteredGroupsForStaffAndEmbedsSinglePdf()
    {
        $year = get_current_year();
        $prof = $this->createPageUser('ist9803', 'Professor Report', ROLE_PROF);
        $student = $this->createPageUser('ist9804', 'Grouped Student', ROLE_STUDENT);
        $group = $this->createGroup(33, $year, [$student]);
        $group->hash_final_report = 'reporthash33';

        $deadline = new \Deadline($year);
        $deadline->final_report = new \DateTimeImmutable('-1 day');

        $oldEntityManager = $this->mockReportEntityManager($deadline);
        $GLOBALS['__page_test_filter_result'] = [$group];

        try {
            $this->setUpPageRuntime('report', $prof);
            $this->runPage('report');

            $this->assertNull($GLOBALS['form']);
            $this->assertSame(
                [
                    [
                        'Group' => 33,
                        'PDF' => [
                            'label' => 'link',
                            'url' => 'index.php?download=' . $group->id . '&page=report',
                        ],
                    ],
                ],
                $GLOBALS['table']
            );
            $this->assertSame(
                'index.php?download=' . $group->id . '&page=report',
                $GLOBALS['embed_file']
            );
            $this->assertCount(1, $GLOBALS['__page_test_eval_boxes']);
            $this->assertSame($group, $GLOBALS['__page_test_eval_boxes'][0]['group']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testReportPageDownloadRejectsWithoutPermissions()
    {
        $result = $this->runReportDownloadScript(false);

        $this->assertSame(0, $result['exit_code']);
        $this->assertSame('No permissions', $result['output']);
    }

    public function testReportPageDownloadOutputsPdfContentWhenAllowed()
    {
        $content = "%PDF-1.4\nreport test\n%%EOF\n";
        $hash = sha1($content);
        $path = $this->uploadPath($hash);
        file_put_contents($path, $content);

        try {
            $result = $this->runReportDownloadScript(true, $hash);

            $this->assertSame(0, $result['exit_code']);
            $this->assertSame(rtrim($content, "\n"), $result['output']);
        } finally {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    private function createGroup(int $number, int $year, array $students): \ProjGroup
    {
        return $this->createPageGroup(
            $number,
            $year,
            $students,
            groupId: $number * 100,
            shiftId: $number * 10
        );
    }

    private function mockReportEntityManager(
        \Deadline $deadline,
        ?\ProjGroup $downloadGroup = null
    ): mixed {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($deadline, $downloadGroup) {
            public function __construct(
                private \Deadline $deadline,
                private ?\ProjGroup $downloadGroup
            ) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'Deadline') {
                    return $this->deadline;
                }

                if ($entity === 'ProjGroup') {
                    return $this->downloadGroup;
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

    private function createUploadedPdf(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'pic1_report_pdf_');
        $contents = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
        file_put_contents($path, $contents);

        return [
            new UploadedFile($path, 'report.pdf', 'application/pdf', null, true),
            $path,
            sha1($contents),
        ];
    }

    private function uploadPath(string $hash): string
    {
        return dirname(__DIR__, 3) . '/uploads/' . $hash;
    }

    private function runReportDownloadScript(bool $allowed, string $hash = 'downloadhash'): array
    {
        $script = sys_get_temp_dir() . '/pic1_report_' . bin2hex(random_bytes(4)) . '.php';
        $bootstrap = var_export(dirname(__DIR__, 3) . '/tests/bootstrap.php', true);
        $pageTestCase = var_export(dirname(__DIR__) . '/Pages/PageTestCase.php', true);
        $page = var_export(dirname(__DIR__, 3) . '/pages/report.php', true);
        $year = get_current_year();
        $userRole = $allowed ? 'ROLE_PROF' : 'ROLE_TA';
        $shiftProfAssignment = $allowed
            ? '$shift->prof = $GLOBALS[\'__page_test_user\'];'
            : '$shift->prof = new User(\'ist9910\', \'Other Professor\', \'other@example.com\', \'\', ROLE_PROF, false);';

        $code = <<<PHP
<?php
require $bootstrap;
require $pageTestCase;
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_GET = ['page' => 'report', 'download' => 3300];
\$_POST = [];
\$_REQUEST = \$_GET;
\$GLOBALS['page'] = 'report';
\$GLOBALS['__page_test_user'] = new User('ist9999', 'Download User', 'ist9999@example.com', '', $userRole, false);
\$GLOBALS['entityManager'] = new class {
    public function find(string \$entity, \$id): mixed
    {
        if (\$entity === 'Deadline') {
            \$deadline = new Deadline($year);
            \$deadline->final_report = new DateTimeImmutable('+1 day');
            return \$deadline;
        }

        if (\$entity === 'ProjGroup') {
            \$shift = new Shift('T1', $year);
            $shiftProfAssignment
            \$group = new ProjGroup(33, $year, \$shift);
            \$group->id = 3300;
            \$group->hash_final_report = '$hash';
            return \$group;
        }

        return null;
    }
};
require $page;
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
        }
    }
}

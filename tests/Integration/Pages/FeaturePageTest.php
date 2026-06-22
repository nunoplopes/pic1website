<?php

namespace Tests\Integration\Pages;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;



class FeaturePageTest extends PageTestCase
{
    public function testFeaturePageTerminatesWhenStudentHasNoGroup()
    {
        $year = get_current_year();
        $deadline = new \Deadline($year);
        $deadline->feature_selection = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockFeatureEntityManager($deadline);

        try {
            $this->setUpPageRuntime(
                'feature',
                $this->createPageUser('ist9700', 'No Group Student', ROLE_STUDENT)
            );

            $this->expectException(PageTerminated::class);
            $this->expectExceptionMessage('Student is not in a group');
            $this->runPage('feature');
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testFeaturePageBuildsStudentUploadFormTableAndEvalBox()
    {
        $year = get_current_year();
        $student = $this->createPageUser('ist9701', 'Feature Student', ROLE_STUDENT);
        $group = $this->createGroup(17, $year, [$student]);
        $group->url_proposal = 'https://example.org/issues/17';
        $group->hash_proposal_file = 'featurehash17';
        $group->allow_modifications_date = new \DateTimeImmutable('+2 days');

        $deadline = new \Deadline($year);
        $deadline->feature_selection = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockFeatureEntityManager($deadline);

        try {
            $this->setUpPageRuntime('feature', $student);
            $this->runPage('feature');

            $this->assertSame(
                'You can submit this form multiple times until the deadline. Only the last submission will be considered.',
                $GLOBALS['info_message']
            );
            $this->assertNotNull($GLOBALS['form']);
            $this->assertTrue($GLOBALS['form']->has('url'));
            $this->assertTrue($GLOBALS['form']->has('file'));
            $this->assertTrue($GLOBALS['form']->has('submit'));
            $this->assertSame(
                'https://example.org/issues/17',
                $GLOBALS['form']->get('url')->getData()
            );

            $this->assertSame(
                [
                    [
                        'Group' => 17,
                        'Issue URL' => [
                            'label' => 'link',
                            'url' => 'https://example.org/issues/17',
                        ],
                        'PDF' => [
                            'label' => 'link',
                            'url' => 'index.php?download=' . $group->id . '&page=feature',
                        ],
                    ],
                ],
                $GLOBALS['table']
            );
            $this->assertSame(
                'index.php?download=' . $group->id . '&page=feature',
                $GLOBALS['embed_file']
            );
            $this->assertCount(1, $GLOBALS['__page_test_eval_boxes']);
            $this->assertSame('feature', $GLOBALS['__page_test_eval_boxes'][0]['page']);
            $this->assertSame($group, $GLOBALS['__page_test_eval_boxes'][0]['group']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testFeaturePageUploadsPdfAndStoresProposalUrl()
    {
        $year = get_current_year();
        $student = $this->createPageUser('ist9702', 'Upload Student', ROLE_STUDENT);
        $group = $this->createGroup(18, $year, [$student]);
        $group->allow_modifications_date = new \DateTimeImmutable('+2 days');

        $deadline = new \Deadline($year);
        $deadline->feature_selection = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockFeatureEntityManager($deadline);
        [$file, $tmpPath, $expectedHash] = $this->createUploadedPdf();
        $uploadedPath = $this->uploadPath($expectedHash);

        try {
            $this->setUpPageRuntime(
                'feature',
                $student,
                'POST',
                [],
                [
                    'form' => [
                        'url' => 'https://example.org/issues/18',
                        'submit' => '',
                    ],
                ]
            );

            global $request;
            $request = Request::create(
                '/index.php?page=feature',
                'POST',
                $_POST,
                [],
                ['form' => ['file' => $file]]
            );

            $this->runPage('feature');

            $this->assertSame('File uploaded successfully!', $GLOBALS['success_message']);
            $this->assertSame('https://example.org/issues/18', $group->url_proposal);
            $this->assertSame($expectedHash, $group->hash_proposal_file);
            $this->assertFileExists($uploadedPath);
            $this->assertSame(
                'index.php?download=' . $group->id . '&page=feature',
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

    public function testFeaturePageRejectsFeatureAlreadyChosenByAnotherGroup()
    {
        $year = get_current_year();
        $student = $this->createPageUser('ist9703', 'Duplicate Student', ROLE_STUDENT);
        $group = $this->createGroup(19, $year, [$student]);
        $group->allow_modifications_date = new \DateTimeImmutable('+2 days');

        $otherStudent = $this->createPageUser('ist9704', 'Other Student', ROLE_STUDENT);
        $otherGroup = $this->createGroup(20, $year, [$otherStudent]);

        $deadline = new \Deadline($year);
        $deadline->feature_selection = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockFeatureEntityManager(
            $deadline,
            groupByUrl: $otherGroup
        );
        [$file, $tmpPath, $expectedHash] = $this->createUploadedPdf();
        $uploadedPath = $this->uploadPath($expectedHash);

        try {
            $this->setUpPageRuntime(
                'feature',
                $student,
                'POST',
                [],
                [
                    'form' => [
                        'url' => 'https://example.org/issues/19',
                        'submit' => '',
                    ],
                ]
            );

            global $request;
            $request = Request::create(
                '/index.php?page=feature',
                'POST',
                $_POST,
                [],
                ['form' => ['file' => $file]]
            );

            $this->expectException(PageTerminated::class);
            $this->expectExceptionMessage('This feature has been selected by another group already');
            $this->runPage('feature');
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

    public function testFeaturePageUsesFilteredGroupsForStaffAndEmbedsSinglePdf()
    {
        $year = get_current_year();
        $prof = $this->createPageUser('ist9705', 'Professor Feature', ROLE_PROF);
        $student = $this->createPageUser('ist9706', 'Grouped Student', ROLE_STUDENT);
        $group = $this->createGroup(21, $year, [$student]);
        $group->url_proposal = 'https://example.org/issues/21';
        $group->hash_proposal_file = 'featurehash21';

        $deadline = new \Deadline($year);
        $deadline->feature_selection = new \DateTimeImmutable('-1 day');

        $oldEntityManager = $this->mockFeatureEntityManager($deadline);
        $GLOBALS['__page_test_filter_result'] = [$group];

        try {
            $this->setUpPageRuntime('feature', $prof);
            $this->runPage('feature');

            $this->assertNull($GLOBALS['form']);
            $this->assertSame(
                [
                    [
                        'Group' => 21,
                        'Issue URL' => [
                            'label' => 'link',
                            'url' => 'https://example.org/issues/21',
                        ],
                        'PDF' => [
                            'label' => 'link',
                            'url' => 'index.php?download=' . $group->id . '&page=feature',
                        ],
                    ],
                ],
                $GLOBALS['table']
            );
            $this->assertSame(
                'index.php?download=' . $group->id . '&page=feature',
                $GLOBALS['embed_file']
            );
            $this->assertCount(1, $GLOBALS['__page_test_eval_boxes']);
            $this->assertSame($group, $GLOBALS['__page_test_eval_boxes'][0]['group']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
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

    private function mockFeatureEntityManager(
        \Deadline $deadline,
        ?\ProjGroup $downloadGroup = null,
        ?\ProjGroup $groupByUrl = null
    ): mixed {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($deadline, $downloadGroup, $groupByUrl) {
            public function __construct(
                private \Deadline $deadline,
                private ?\ProjGroup $downloadGroup,
                private ?\ProjGroup $groupByUrl
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

            public function createQueryBuilder(): object
            {
                return new class($this->groupByUrl) {
                    private array $params = [];

                    public function __construct(private ?\ProjGroup $groupByUrl) {}
                    public function from($entity, $alias): self { return $this; }
                    public function select($select): self { return $this; }
                    public function where($where): self { return $this; }
                    public function andWhere($where): self { return $this; }
                    public function setParameter($name, $value): self
                    {
                        $this->params[$name] = $value;
                        return $this;
                    }
                    public function getQuery(): self { return $this; }
                    public function getOneOrNullResult(): ?\ProjGroup
                    {
                        if (($this->params['url'] ?? null) !== null) {
                            return $this->groupByUrl;
                        }

                        return null;
                    }
                };
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
        $path = tempnam(sys_get_temp_dir(), 'pic1_feature_pdf_');
        $contents = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
        file_put_contents($path, $contents);

        return [
            new UploadedFile($path, 'proposal.pdf', 'application/pdf', null, true),
            $path,
            sha1($contents),
        ];
    }

    private function uploadPath(string $hash): string
    {
        return dirname(__DIR__, 3) . '/uploads/' . $hash;
    }
}

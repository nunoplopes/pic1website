<?php

namespace Tests\Integration\Pages;




class BugsPageTest extends PageTestCase
{
    public function testBugsPageBuildsStudentFormWithExistingBugAndTable()
    {
        $year = get_current_year();
        $student = $this->createPageUser('ist9601', 'Alice Student', ROLE_STUDENT);
        $group = $this->createGroup(1001, $year, [$student]);
        $group->repository = 'github:owner/repo';
        $group->allow_modifications_date = new \DateTimeImmutable('-1 day');

        $deadline = new \Deadline($year);
        $deadline->bug_selection = new \DateTimeImmutable('+1 day');

        $bug = new \SelectedBug();
        $bug->user = $student;
        $bug->year = $year;
        $bug->issue_url = 'https://example.org/issues/42';
        $bug->repro_url = '';
        $bug->description = 'Steps to reproduce the bug.';

        $oldEntityManager = $this->mockBugsEntityManager(
            deadline: $deadline,
            bugsByUser: [$student->id => $bug],
            bugsByIssue: [$bug->issue_url => $bug]
        );

        try {
            $this->setUpPageRuntime('bugs', $student);
            $this->runPage('bugs');

            $this->assertSame(
                'You can submit this form multiple times until the deadline. Only the last submission will be considered.',
                $GLOBALS['info_message']
            );
            $this->assertNotNull($GLOBALS['form']);
            $this->assertTrue($GLOBALS['form']->has('issue_url'));
            $this->assertTrue($GLOBALS['form']->has('repro_url'));
            $this->assertTrue($GLOBALS['form']->has('description'));
            $this->assertSame('https://example.org/issues/42', $GLOBALS['form']->get('issue_url')->getData());
            $this->assertSame('', $GLOBALS['form']->get('repro_url')->getData());
            $this->assertSame('Steps to reproduce the bug.', $GLOBALS['form']->get('description')->getData());

            $this->assertCount(1, $GLOBALS['table']);
            $row = $GLOBALS['table'][0];
            $this->assertSame('Alice Student', $row['Student']);
            $this->assertSame('link', $row['Issue']['label']);
            $this->assertSame('https://example.org/issues/42', $row['Issue']['url']);
            $this->assertSame(['longdata' => 'Steps to reproduce the bug.'], $row['Description']);
            $this->assertSame('', $row['Video']);
            $this->assertTrue($row['_large_table']);

            $this->assertCount(1, $GLOBALS['__page_test_eval_boxes']);
            $this->assertSame($group, $GLOBALS['__page_test_eval_boxes'][0]['group']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testBugsPageCreatesNewBugAndPersistsIt()
    {
        $year = get_current_year();
        $student = $this->createPageUser('ist9602', 'Bob Student', ROLE_STUDENT);
        $group = $this->createGroup(1002, $year, [$student]);

        $deadline = new \Deadline($year);
        $deadline->bug_selection = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockBugsEntityManager(
            deadline: $deadline,
            bugsByUser: [],
            bugsByIssue: []
        );

        try {
            $this->setUpPageRuntime(
                'bugs',
                $student,
                'POST',
                [],
                [
                    'form' => [
                        'issue_url' => 'https://example.org/issues/99',
                        'repro_url' => '',
                        'description' => 'New bug description',
                        'submit' => '',
                    ],
                ]
            );
            $this->runPage('bugs');

            $saved = $GLOBALS['__page_test_saved_bugs'] ?? [];
            $this->assertCount(1, $saved);
            $this->assertInstanceOf(\SelectedBug::class, $saved[0]);
            $this->assertSame($student, $saved[0]->user);
            $this->assertSame($year, $saved[0]->year);
            $this->assertSame('https://example.org/issues/99', $saved[0]->issue_url);
            $this->assertSame('', $saved[0]->repro_url);
            $this->assertSame('New bug description', $saved[0]->description);

            $this->assertCount(1, $GLOBALS['table']);
            $this->assertSame('Bob Student', $GLOBALS['table'][0]['Student']);
            $this->assertSame('https://example.org/issues/99', $GLOBALS['table'][0]['Issue']['url']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testBugsPageTerminatesWhenStudentSubmitsWithoutGroup()
    {
        $year = get_current_year();
        $student = $this->createPageUser('ist9603', 'Lonely Student', ROLE_STUDENT);

        $deadline = new \Deadline($year);
        $deadline->bug_selection = new \DateTimeImmutable('+1 day');

        $oldEntityManager = $this->mockBugsEntityManager(
            deadline: $deadline,
            bugsByUser: [],
            bugsByIssue: []
        );

        try {
            $this->setUpPageRuntime(
                'bugs',
                $student,
                'POST',
                [],
                [
                    'form' => [
                        'issue_url' => 'https://example.org/issues/123',
                        'repro_url' => '',
                        'description' => 'No group bug',
                        'submit' => '',
                    ],
                ]
            );

            $this->expectException(PageTerminated::class);
            $this->expectExceptionMessage('Student is not in a group');
            $this->runPage('bugs');
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    private function createGroup(int $number, int $year, array $students): \ProjGroup
    {
        return $this->createPageGroup($number, $year, $students, groupId: $number);
    }

    private function mockBugsEntityManager(
        \Deadline $deadline,
        array $bugsByUser,
        array $bugsByIssue
    ): mixed {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['__page_test_saved_bugs'] = [];
        $GLOBALS['entityManager'] = new class($deadline, $bugsByUser, $bugsByIssue) {
            public function __construct(
                public \Deadline $deadline,
                public array $bugsByUser,
                public array $bugsByIssue
            ) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'Deadline') {
                    return $this->deadline;
                }

                return null;
            }

            public function createQueryBuilder(): object
            {
                return new class($this) {
                    private array $params = [];

                    public function __construct(private object $parent) {}
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
                    public function getOneOrNullResult(): ?\SelectedBug
                    {
                        if (isset($this->params['url'])) {
                            return $this->parent->bugsByIssue[$this->params['url']] ?? null;
                        }
                        if (isset($this->params['user'])) {
                            return $this->parent->bugsByUser[$this->params['user']] ?? null;
                        }
                        return null;
                    }
                };
            }

            public function persist($obj): void
            {
                if ($obj instanceof \SelectedBug) {
                    $this->bugsByUser[$obj->user->id] = $obj;
                    $this->bugsByIssue[$obj->issue_url] = $obj;
                    $GLOBALS['__page_test_saved_bugs'][] = $obj;
                }
            }

            public function flush(): void
            {
            }
        };

        return $oldEntityManager;
    }
}

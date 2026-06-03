<?php

namespace Tests\Integration\Pages;




class GradingPageTest extends PageTestCase
{
    public function testGradingPageAddsMilestone()
    {
        $year = get_current_year();
        $oldEntityManager = $this->mockGradingEntityManager(
            milestonesByYear: [$year => []],
            milestoneYears: [],
            finalGradesByYear: [],
            milestonesById: []
        );
        $GLOBALS['__page_test_filter_result'] = $year;

        try {
            $prof = $this->createPageUser('ist9930', 'Professor Grading', ROLE_PROF);
            $this->setUpPageRuntime(
                'grading',
                $prof,
                'POST',
                [],
                [
                    'add_milestone' => [
                        'name' => 'Milestone X',
                        'submit' => '',
                    ],
                ]
            );
            $this->runPage('grading');

            $saved = $GLOBALS['entityManager']->saved[0] ?? null;
            $this->assertInstanceOf(\Milestone::class, $saved);
            $this->assertSame($year, $saved->year);
            $this->assertSame('Milestone X', $saved->name);
            $this->assertCount(1, $GLOBALS['entityManager']->saved);
            $this->assertSame(1, $GLOBALS['entityManager']->flushCount);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testGradingPageCopiesMilestonesAndFinalFormulaFromSourceYear()
    {
        $year = get_current_year();
        $sourceYear = $year - 1;

        $sourceMilestone = new \Milestone($sourceYear, 'M1');
        $sourceMilestone->description = 'Imported milestone';
        $sourceMilestone->field1 = 'Code Quality';
        $sourceMilestone->points1 = 200;
        $sourceMilestone->range1 = 100;

        $sourceFinal = new \FinalGrade();
        $sourceFinal->year = $sourceYear;
        $sourceFinal->formula = 'M1';

        $oldEntityManager = $this->mockGradingEntityManager(
            milestonesByYear: [
                $year => [],
                $sourceYear => [$sourceMilestone],
            ],
            milestoneYears: [['year' => $sourceYear]],
            finalGradesByYear: [$sourceYear => $sourceFinal],
            milestonesById: []
        );
        $GLOBALS['__page_test_filter_result'] = $year;

        try {
            $prof = $this->createPageUser('ist9931', 'Professor Copy', ROLE_PROF);
            $this->setUpPageRuntime(
                'grading',
                $prof,
                'POST',
                [],
                [
                    'copy' => [
                        'source_year' => (string)$sourceYear,
                        'copy' => '',
                    ],
                ]
            );
            $this->runPage('grading');

            $this->assertSame(
                "Grading copied successfully from year $sourceYear.",
                $GLOBALS['success_message']
            );
            $this->assertCount(2, $GLOBALS['entityManager']->saved);
            $this->assertInstanceOf(\Milestone::class, $GLOBALS['entityManager']->saved[0]);
            $this->assertSame($year, $GLOBALS['entityManager']->saved[0]->year);
            $this->assertSame('M1', $GLOBALS['entityManager']->saved[0]->name);
            $this->assertSame('Imported milestone', $GLOBALS['entityManager']->saved[0]->description);
            $this->assertInstanceOf(\FinalGrade::class, $GLOBALS['entityManager']->saved[1]);
            $this->assertSame($year, $GLOBALS['entityManager']->saved[1]->year);
            $this->assertSame('M1', $GLOBALS['entityManager']->saved[1]->formula);
            $this->assertSame(1, $GLOBALS['entityManager']->flushCount);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testGradingPageBuildsFinalGradeFormAndTerminates()
    {
        $year = get_current_year();
        $finalGrade = new \FinalGrade();
        $finalGrade->year = $year;
        $finalGrade->formula = 'M1+M2';

        $oldEntityManager = $this->mockGradingEntityManager(
            milestonesByYear: [$year => []],
            milestoneYears: [],
            finalGradesByYear: [$year => $finalGrade],
            milestonesById: []
        );
        $GLOBALS['__page_test_filter_result'] = $year;

        try {
            $prof = $this->createPageUser('ist9932', 'Professor Final', ROLE_PROF);
            $this->setUpPageRuntime('grading', $prof, 'GET', ['final' => 1]);

            try {
                $this->runPage('grading');
                $this->fail('Expected grading page to terminate after building final grade form.');
            } catch (PageTerminated) {
            }

            $this->assertNotNull($GLOBALS['form']);
            $this->assertTrue($GLOBALS['form']->has('final'));
            $this->assertTrue($GLOBALS['form']->has('formula'));
            $this->assertTrue($GLOBALS['form']->has('submit'));
            $this->assertSame('M1+M2', $GLOBALS['form']->get('formula')->getData());
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testGradingPageSavesFinalGradeFormulaFromFinalFormSubmission()
    {
        $year = get_current_year();
        $oldEntityManager = $this->mockGradingEntityManager(
            milestonesByYear: [$year => []],
            milestoneYears: [],
            finalGradesByYear: [],
            milestonesById: []
        );
        $GLOBALS['__page_test_filter_result'] = $year;

        try {
            $prof = $this->createPageUser('ist9933', 'Professor Final Save', ROLE_PROF);
            $this->setUpPageRuntime(
                'grading',
                $prof,
                'POST',
                ['final' => 1],
                [
                    'form' => [
                        'final' => '1',
                        'formula' => 'M1+M2',
                        'submit' => '',
                    ],
                ]
            );

            set_error_handler(static function (int $severity, string $message): bool {
                return str_contains($message, 'Undefined variable $data');
            });

            try {
                try {
                    $this->runPage('grading');
                    $this->fail('Expected grading page to terminate after saving final grade.');
                } catch (PageTerminated) {
                }
            } finally {
                restore_error_handler();
            }

            $saved = $GLOBALS['entityManager']->saved[0] ?? null;
            $this->assertInstanceOf(\FinalGrade::class, $saved);
            $this->assertSame($year, $saved->year);
            $this->assertSame('M1+M2', $saved->formula);
            $this->assertCount(1, $GLOBALS['entityManager']->saved);
            $this->assertSame(1, $GLOBALS['entityManager']->flushCount);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testGradingPageEditsMilestoneAndBuildsMilestonesTable()
    {
        $year = get_current_year();
        $milestone = new \Milestone($year, 'M1');
        $milestone->id = 901;
        $milestone->description = 'Original description';
        $milestone->field1 = 'Code Quality';
        $milestone->points1 = 100;
        $milestone->range1 = 50;

        $oldEntityManager = $this->mockGradingEntityManager(
            milestonesByYear: [$year => [$milestone]],
            milestoneYears: [],
            finalGradesByYear: [],
            milestonesById: [$milestone->id => $milestone]
        );
        $GLOBALS['__page_test_filter_result'] = $year;

        try {
            $prof = $this->createPageUser('ist9934', 'Professor Edit', ROLE_PROF);
            $this->setUpPageRuntime(
                'grading',
                $prof,
                'POST',
                ['id' => $milestone->id],
                [
                    'form' => [
                        'id' => '999',
                        'year' => '1999',
                        'name' => 'M1 Updated',
                        'description' => 'Updated description',
                        'page' => 'grades',
                        'field1' => 'Testing',
                        'points1' => '200',
                        'range1' => '100',
                        'field2' => '',
                        'points2' => '0',
                        'range2' => '0',
                        'field3' => '',
                        'points3' => '0',
                        'range3' => '0',
                        'field4' => '',
                        'points4' => '0',
                        'range4' => '0',
                        'submit' => '',
                    ],
                ]
            );
            $this->runPage('grading');

            $this->assertSame(901, $milestone->id);
            $this->assertSame($year, $milestone->year);
            $this->assertSame('M1 Updated', $milestone->name);
            $this->assertSame('Updated description', $milestone->description);
            $this->assertSame('grades', $milestone->page);
            $this->assertSame('Testing', $milestone->field1);
            $this->assertSame(200, $milestone->points1);
            $this->assertSame(100, $milestone->range1);
            $this->assertSame('Database updated!', $GLOBALS['success_message']);

            $this->assertCount(1, $GLOBALS['table']);
            $row = $GLOBALS['table'][0];
            $this->assertSame('M1 Updated', $row['name']['label']);
            $this->assertSame('index.php?id=901&page=grading', $row['name']['url']);
            $this->assertTrue($row['_large_table']);
            $this->assertSame('Updated description', $row['description']);
            $this->assertSame('grades', $row['page']);
            $this->assertSame('Testing', $row['field1']);
            $this->assertSame(200, $row['points1']);
            $this->assertSame(100, $row['range1']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testGradingPageRejectsInvalidMilestoneId()
    {
        $result = $this->runGradingScriptWithInvalidMilestone();

        $this->assertSame(0, $result['exit_code']);
        $this->assertSame('Invalid milestone', $result['output']);
    }

    private function mockGradingEntityManager(
        array $milestonesByYear,
        array $milestoneYears,
        array $finalGradesByYear,
        array $milestonesById
    ): mixed {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($milestonesByYear, $milestoneYears, $finalGradesByYear, $milestonesById) {
            public array $saved = [];
            public int $flushCount = 0;

            public function __construct(
                private array $milestonesByYear,
                private array $milestoneYears,
                private array $finalGradesByYear,
                private array $milestonesById
            ) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'FinalGrade') {
                    return $this->finalGradesByYear[$id] ?? null;
                }
                if ($entity === 'Milestone') {
                    return $this->milestonesById[$id] ?? null;
                }

                return null;
            }

            public function getRepository(string $entity): object
            {
                if ($entity === 'Milestone') {
                    return new class($this->milestonesByYear) {
                        public function __construct(private array $milestonesByYear) {}

                        public function findByYear($year, $order = null): array
                        {
                            return $this->milestonesByYear[$year] ?? [];
                        }
                    };
                }

                throw new \RuntimeException("Unexpected repository lookup: $entity");
            }

            public function createQueryBuilder(): object
            {
                return new class($this->milestoneYears) {
                    public function __construct(private array $milestoneYears) {}
                    public function from($entity, $alias): self { return $this; }
                    public function select($select): self { return $this; }
                    public function distinct(): self { return $this; }
                    public function orderBy($field, $direction = null): self { return $this; }
                    public function getQuery(): self { return $this; }
                    public function getArrayResult(): array { return $this->milestoneYears; }
                };
            }

            public function persist($obj): void
            {
                $this->saved[] = $obj;
            }

            public function flush(): void
            {
                $this->flushCount++;
            }
        };

        return $oldEntityManager;
    }

    private function runGradingScriptWithInvalidMilestone(): array
    {
        $script = sys_get_temp_dir() . '/pic1_grading_' . bin2hex(random_bytes(4)) . '.php';
        $bootstrap = var_export(dirname(__DIR__, 3) . '/tests/bootstrap.php', true);
        $pageTestCase = var_export(dirname(__DIR__) . '/Pages/PageTestCase.php', true);
        $page = var_export(dirname(__DIR__, 3) . '/pages/grading.php', true);
        $year = get_current_year();

        $code = <<<PHP
<?php
require $bootstrap;
require $pageTestCase;
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_GET = ['page' => 'grading', 'id' => 999];
\$_POST = [];
\$_REQUEST = \$_GET;
\$GLOBALS['__page_test_filter_result'] = $year;
\$request = Symfony\Component\HttpFoundation\Request::create('/index.php?page=grading&id=999', 'GET', \$_GET);
\$formFactory = Symfony\Component\Form\Forms::createFormFactoryBuilder()
    ->addExtension(new Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension())
    ->getFormFactory();
\$GLOBALS['page'] = 'grading';
\$GLOBALS['entityManager'] = new class {
    public function find(string \$entity, \$id): mixed
    {
        return null;
    }

    public function getRepository(string \$entity): object
    {
        if (\$entity === 'Milestone') {
            return new class {
                public function findByYear(\$year, \$order = null): array
                {
                    return [];
                }
            };
        }

        throw new RuntimeException('Unexpected repository lookup');
    }

    public function createQueryBuilder(): object
    {
        return new class {
            public function from(\$entity, \$alias): self { return \$this; }
            public function select(\$select): self { return \$this; }
            public function distinct(): self { return \$this; }
            public function orderBy(\$field, \$direction = null): self { return \$this; }
            public function getQuery(): self { return \$this; }
            public function getArrayResult(): array { return []; }
        };
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

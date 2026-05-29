<?php

namespace Tests\Integration\Pages;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;



class GradesPageTest extends PageTestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGradesPageTerminatesWhenFinalFormulaIsMissing()
    {
        $group = $this->createGroup(1001, get_current_year(), [
            $this->createPageUser('ist9401', 'Alice Student', ROLE_STUDENT),
        ]);
        $student = $group->students->first();

        $oldEntityManager = $this->mockGradesEntityManager(
            milestones: [],
            finalGrade: null,
            grades: [],
            usersById: [$student->id => $student]
        );

        try {
            $this->setUpPageRuntime('grades', $student);

            $this->expectException(PageTerminated::class);
            $this->expectExceptionMessage('No final grade formula defined for year ' . get_current_year());
            $this->runPage('grades');
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGradesPageHandlesStudentWithoutGroupAndBinaryFormula()
    {
        $year = get_current_year();
        $student = $this->createPageUser('ist9405', 'Lonely Student', ROLE_STUDENT);

        $milestone = $this->createMilestone($year, 'M1', 'Milestone One', 'Code Quality', 200, 100);
        $finalGrade = $this->createFinalGrade($year, 'M1+M2');

        $oldEntityManager = $this->mockGradesEntityManager(
            milestones: [$milestone],
            finalGrade: $finalGrade,
            grades: [],
            usersById: []
        );

        try {
            $this->setUpPageRuntime('grades', $student);
            $this->runPage('grades');

            $this->assertSame('Final Grade', $GLOBALS['display_formula']['title']);
            $this->assertCount(3, $GLOBALS['display_formula']['items']);
            $this->assertSame('M₁', $GLOBALS['display_formula']['items'][0]['var']);
            $this->assertSame('+', $GLOBALS['display_formula']['items'][1]['var']);
            $this->assertSame('M₂', $GLOBALS['display_formula']['items'][2]['var']);
            $this->assertStringContainsString('Milestone One', $GLOBALS['display_formula']['items'][0]['title']);
            $this->assertSame('', $GLOBALS['display_formula']['items'][2]['title']);
            $this->assertNull($GLOBALS['table']);
            $this->assertNull($GLOBALS['bottom_links']);
            $this->assertNull($GLOBALS['plots']);
            $this->assertSame([], $GLOBALS['__page_test_eval_boxes'] ?? []);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGradesPageBuildsStudentTableAndFormulaDisplay()
    {
        $year = get_current_year();
        $student = $this->createPageUser('ist9402', 'Alice Student', ROLE_STUDENT);
        $group = $this->createGroup(1001, $year, [$student]);

        $milestone = $this->createMilestone($year, 'M1', 'Milestone One', 'Code Quality', 200, 100);
        $grade = $this->createGrade($student, $milestone, 80, lateDays: 0);
        $finalGrade = $this->createFinalGrade($year, 'M1');

        $oldEntityManager = $this->mockGradesEntityManager(
            milestones: [$milestone],
            finalGrade: $finalGrade,
            grades: [$grade],
            usersById: [$student->id => $student]
        );

        try {
            $this->setUpPageRuntime('grades', $student);
            $this->runPage('grades');

            $this->assertCount(1, $GLOBALS['__page_test_eval_boxes']);
            $this->assertSame($group, $GLOBALS['__page_test_eval_boxes'][0]['group']);

            $this->assertSame('Final Grade', $GLOBALS['display_formula']['title']);
            $this->assertSame('M₁', $GLOBALS['display_formula']['items'][0]['var']);
            $this->assertSame('indigo', $GLOBALS['display_formula']['items'][0]['color']);
            $this->assertStringContainsString('Milestone One', $GLOBALS['display_formula']['items'][0]['title']);
            $this->assertStringContainsString('Code Quality: 20.00', $GLOBALS['display_formula']['items'][0]['title']);

            $this->assertCount(1, $GLOBALS['table']);
            $row = $GLOBALS['table'][0];
            $this->assertSame('ist9402', $row['id']);
            $this->assertSame('Alice Student', $row['name']);
            $this->assertTrue($row['_large_table']);
            $this->assertSame('16.00', $row['M1']['text']);
            $this->assertStringContainsString('Milestone One', $row['M1']['tooltip']);
            $this->assertStringContainsString('Code Quality: 16.00', $row['M1']['tooltip']);
            $this->assertSame(16, $row['Final']);
            $this->assertNull($GLOBALS['plots']);
            $this->assertNull($GLOBALS['bottom_links']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGradesPageBuildsProfessorPlotsAndDownloadLinks()
    {
        $year = get_current_year();
        $student1 = $this->createPageUser('ist9403', 'Alice Student', ROLE_STUDENT);
        $student2 = $this->createPageUser('ist9404', 'Bob Student', ROLE_STUDENT);
        $group1 = $this->createGroup(1001, $year, [$student1]);
        $group2 = $this->createGroup(2001, $year, [$student2]);

        $milestone = $this->createMilestone($year, 'M1', 'Milestone One', 'Code Quality', 200, 100);
        $grade1 = $this->createGrade($student1, $milestone, 80, lateDays: 0);
        $grade2 = $this->createGrade($student2, $milestone, 50, lateDays: 6);
        $finalGrade = $this->createFinalGrade($year, 'M1');

        $oldEntityManager = $this->mockGradesEntityManager(
            milestones: [$milestone],
            finalGrade: $finalGrade,
            grades: [$grade1, $grade2],
            usersById: [
                $student1->id => $student1,
                $student2->id => $student2,
            ]
        );

        $GLOBALS['__page_test_filter_result'] = [$group1, $group2];

        try {
            $this->setUpPageRuntime(
                'grades',
                $this->createPageUser('ist9400', 'Professor Admin', ROLE_PROF)
            );
            $this->runPage('grades');

            $this->assertCount(2, $GLOBALS['table']);
            $this->assertSame(16, $GLOBALS['table'][0]['Final']);
            $this->assertSame(5, $GLOBALS['table'][1]['Final']);

            $this->assertNotNull($GLOBALS['plots']);
            $this->assertSame(1, $GLOBALS['plots']['Overall'][16]);
            $this->assertSame(1, $GLOBALS['plots']['Overall'][5]);
            $this->assertSame(1, $GLOBALS['plots']['Group 1'][16]);
            $this->assertSame(1, $GLOBALS['plots']['Group 2'][5]);

            $this->assertCount(2, $GLOBALS['bottom_links']);
            $this->assertSame('Download grades of group 1', $GLOBALS['bottom_links'][0]['label']);
            $this->assertSame(
                'index.php?download=1&all_shifts=1&page=grades',
                $GLOBALS['bottom_links'][0]['url']
            );
            $this->assertSame('Download grades of group 2', $GLOBALS['bottom_links'][1]['label']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGradesPageHelperRejectsUnknownNodeType()
    {
        $year = get_current_year();
        $student = $this->createPageUser('ist9406', 'Helper Student', ROLE_STUDENT);

        $milestone = $this->createMilestone($year, 'M1', 'Milestone One', 'Code Quality', 200, 100);
        $finalGrade = $this->createFinalGrade($year, 'M1');

        $oldEntityManager = $this->mockGradesEntityManager(
            milestones: [$milestone],
            finalGrade: $finalGrade,
            grades: [],
            usersById: []
        );

        try {
            $this->setUpPageRuntime('grades', $student);
            $this->runPage('grades');

            $this->expectException(\AssertionError::class);
            gen_formula_data(new \Symfony\Component\ExpressionLanguage\Node\Node());
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    private function createGroup(int $number, int $year, array $students): \ProjGroup
    {
        return $this->createPageGroup($number, $year, $students, groupId: $number);
    }

    private function createMilestone(
        int $year,
        string $name,
        string $description,
        string $field1,
        int $points1,
        int $range1
    ): \Milestone {
        $milestone = new \Milestone($year, $name);
        $milestone->description = $description;
        $milestone->field1 = $field1;
        $milestone->points1 = $points1;
        $milestone->range1 = $range1;
        return $milestone;
    }

    private function createGrade(\User $user, \Milestone $milestone, int $field1, int $lateDays): \Grade
    {
        $grade = new \Grade();
        $grade->user = $user;
        $grade->milestone = $milestone;
        $grade->field1 = $field1;
        $grade->field2 = null;
        $grade->field3 = null;
        $grade->field4 = null;
        $grade->late_days = $lateDays;
        return $grade;
    }

    private function createFinalGrade(int $year, string $formula): \FinalGrade
    {
        $finalGrade = new \FinalGrade();
        $finalGrade->year = $year;
        $finalGrade->formula = $formula;
        return $finalGrade;
    }

    private function mockGradesEntityManager(
        array $milestones,
        ?\FinalGrade $finalGrade,
        array $grades,
        array $usersById
    ): mixed {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($milestones, $finalGrade, $grades, $usersById) {
            public function __construct(
                private array $milestones,
                private ?\FinalGrade $finalGrade,
                private array $grades,
                private array $usersById
            ) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'FinalGrade') {
                    return $this->finalGrade;
                }

                if ($entity === 'User') {
                    return $this->usersById[$id] ?? null;
                }

                return null;
            }

            public function getRepository(string $entity): object
            {
                if ($entity === 'Milestone') {
                    return new class($this->milestones) {
                        public function __construct(private array $milestones) {}
                        public function findByYear($year): array
                        {
                            return $this->milestones;
                        }
                    };
                }

                throw new \RuntimeException("Unexpected repository lookup: $entity");
            }

            public function createQueryBuilder(): object
            {
                return new class($this->grades) {
                    public function __construct(private array $grades) {}
                    public function from($entity, $alias): self { return $this; }
                    public function select($select): self { return $this; }
                    public function join($join, $alias): self { return $this; }
                    public function where($where): self { return $this; }
                    public function setParameter($name, $value): self { return $this; }
                    public function getQuery(): self { return $this; }
                    public function getResult(): array { return $this->grades; }
                };
            }
        };

        return $oldEntityManager;
    }
}

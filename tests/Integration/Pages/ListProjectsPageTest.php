<?php

namespace Tests\Integration\Pages;



class ListProjectsPageTest extends PageTestCase
{
    public function testStudentListProjectsUsesUserGroups()
    {
        $student = $this->createPageUser('ist1000', 'Alice Student', ROLE_STUDENT);
        $group = $this->createGroup(7, 2025, [$student]);

        $this->setUpPageRuntime('listprojects', $student);
        $this->runPage('listprojects');

        $this->assertCount(1, $GLOBALS['table']);
        $this->assertSame(7, $GLOBALS['table'][0]['Group']['label']);
        $this->assertSame(
            "Alice Student (ist1000)",
            $GLOBALS['table'][0]['Students']
        );
        $this->assertSame(
            'index.php?id=' . $group->id . '&page=listproject',
            $GLOBALS['table'][0]['Group']['url']
        );
    }

    public function testTaListProjectsUsesFilteredGroups()
    {
        $ta = $this->createPageUser('ist2000', 'Taylor Assistant', ROLE_TA);
        $student = $this->createPageUser('ist2001', 'Sam Student', ROLE_STUDENT);
        $group = $this->createGroup(3, 2025, [$student]);

        $GLOBALS['__page_test_filter_result'] = [$group];

        $this->setUpPageRuntime('listprojects', $ta);
        $this->runPage('listprojects');

        $this->assertCount(1, $GLOBALS['table']);
        $this->assertSame(3, $GLOBALS['table'][0]['Group']['label']);
        $this->assertSame(
            "Sam Student (ist2001)",
            $GLOBALS['table'][0]['Students']
        );
    }

    public function testListProjectsBuildsStudentNamesAndGroupLinks()
    {
        $studentA = $this->createPageUser('ist3001', 'Alice Student', ROLE_STUDENT);
        $studentB = $this->createPageUser('ist3002', 'Bob Student', ROLE_STUDENT);
        $group = $this->createGroup(11, 2025, [$studentA, $studentB]);

        $GLOBALS['__page_test_filter_result'] = [$group];

        $this->setUpPageRuntime(
            'listprojects',
            $this->createPageUser('ist3000', 'Professor Example', ROLE_PROF)
        );
        $this->runPage('listprojects');

        $this->assertSame(
            [
                [
                    'Group' => [
                        'label' => 11,
                        'url' => 'index.php?id=' . $group->id . '&page=listproject',
                    ],
                    'Students' => "Alice Student (ist3001)\nBob Student (ist3002)",
                ],
            ],
            $GLOBALS['table']
        );
    }

    public function testListProjectsHandlesNoGroups()
    {
        $student = $this->createPageUser('ist4000', 'Empty Student', ROLE_STUDENT);

        $this->setUpPageRuntime('listprojects', $student);
        $this->runPage('listprojects');

        $this->assertSame([], $GLOBALS['table']);
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
}

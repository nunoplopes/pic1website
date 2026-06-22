<?php

namespace Tests\Integration\Pages;



class DeadlinesPageTest extends PageTestCase
{
    public function testDeadlinesPageBuildsFormWithReadonlyYear()
    {
        $year = get_current_year();
        $deadline = new \Deadline($year);
        $oldEntityManager = $this->mockDeadlineEntityManager($deadline);

        try {
            $this->setUpPageRuntime(
                'deadlines',
                $this->createPageUser('ist9000', 'Professor Deadlines', ROLE_PROF)
            );
            $this->runPage('deadlines');

            $this->assertSame($year, $GLOBALS['__page_test_deadline_year']);
            $this->assertNotNull($GLOBALS['form']);
            $this->assertTrue($GLOBALS['form']->has('year'));
            $this->assertTrue($GLOBALS['form']->has('proj_proposal'));
            $this->assertTrue($GLOBALS['form']->has('bug_selection'));
            $this->assertTrue($GLOBALS['form']->has('feature_selection'));
            $this->assertTrue($GLOBALS['form']->has('patch_submission'));
            $this->assertTrue($GLOBALS['form']->has('final_report'));
            $this->assertTrue($GLOBALS['form']->has('submit'));
            $this->assertTrue($GLOBALS['form']->get('year')->getConfig()->getOption('disabled'));
            $this->assertFalse($GLOBALS['form']->get('submit')->getConfig()->getOption('disabled'));
            $this->assertNull($GLOBALS['success_message']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testDeadlinesPageUpdatesEditableFieldsButKeepsYearReadonly()
    {
        $year = get_current_year();
        $deadline = new \Deadline($year);
        $deadline->proj_proposal = new \DateTimeImmutable('2025-01-01 10:00');
        $deadline->bug_selection = new \DateTimeImmutable('2025-01-02 10:00');
        $deadline->feature_selection = new \DateTimeImmutable('2025-01-03 10:00');
        $deadline->patch_submission = new \DateTimeImmutable('2025-01-04 10:00');
        $deadline->final_report = new \DateTimeImmutable('2025-01-05 10:00');

        $oldEntityManager = $this->mockDeadlineEntityManager($deadline);

        try {
            $this->setUpPageRuntime(
                'deadlines',
                $this->createPageUser('ist9001', 'Professor Editor', ROLE_PROF),
                'POST',
                [],
                [
                    'form' => [
                        'year' => '1999',
                        'proj_proposal' => '2025-06-01T09:30',
                        'bug_selection' => '2025-06-02T10:30',
                        'feature_selection' => '2025-06-03T11:30',
                        'patch_submission' => '2025-06-04T12:30',
                        'final_report' => '2025-06-05T13:30',
                        'submit' => '',
                    ],
                ]
            );
            $this->runPage('deadlines');

            $this->assertSame($year, $deadline->year);
            $this->assertSame('2025-06-01 09:30', $deadline->proj_proposal->format('Y-m-d H:i'));
            $this->assertSame('2025-06-02 10:30', $deadline->bug_selection->format('Y-m-d H:i'));
            $this->assertSame('2025-06-03 11:30', $deadline->feature_selection->format('Y-m-d H:i'));
            $this->assertSame('2025-06-04 12:30', $deadline->patch_submission->format('Y-m-d H:i'));
            $this->assertSame('2025-06-05 13:30', $deadline->final_report->format('Y-m-d H:i'));
            $this->assertSame('Database updated!', $GLOBALS['success_message']);
            $this->assertTrue($GLOBALS['form']->get('year')->getConfig()->getOption('disabled'));
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    private function mockDeadlineEntityManager(\Deadline $deadline): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($deadline) {
            public function __construct(private \Deadline $deadline) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'Deadline') {
                    $GLOBALS['__page_test_deadline_year'] = $id;
                    return $this->deadline;
                }

                return null;
            }
        };

        return $oldEntityManager;
    }
}

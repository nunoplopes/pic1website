<?php

namespace Tests\Integration\Pages;



class ShiftsPageTest extends PageTestCase
{
    public function testShiftsPageBuildsChoiceFormForCurrentYearShifts()
    {
        $ta = $this->createPageUser('ist9100', 'Taylor Assistant', ROLE_TA);
        $prof = $this->createPageUser('ist9101', 'Professor Example', ROLE_PROF);
        $shift1 = $this->createShift(11, 'T1', $ta);
        $shift2 = $this->createShift(12, 'T2', null);

        $oldEntityManager = $this->mockShiftsEntityManager([$shift1, $shift2], [$ta, $prof], []);

        try {
            $this->setUpPageRuntime(
                'shifts',
                $this->createPageUser('ist9102', 'Professor Admin', ROLE_PROF)
            );
            $this->runPage('shifts');

            $this->assertSame(get_current_year(), $GLOBALS['__page_test_shifts_year']);
            $this->assertNotNull($GLOBALS['form']);
            $this->assertTrue($GLOBALS['form']->has('shift_11'));
            $this->assertTrue($GLOBALS['form']->has('shift_12'));
            $this->assertTrue($GLOBALS['form']->has('submit'));
            $this->assertSame('T1', $GLOBALS['form']->get('shift_11')->getConfig()->getOption('label'));
            $this->assertSame('T2', $GLOBALS['form']->get('shift_12')->getConfig()->getOption('label'));
            $this->assertSame('ist9100', $GLOBALS['form']->get('shift_11')->getData());
            $this->assertNull($GLOBALS['form']->get('shift_12')->getData());
            $this->assertNull($GLOBALS['success_message']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testShiftsPageAssignsSelectedProfessorsAndTas()
    {
        $sudo = $this->createPageUser('ist9200', 'Sudo User', ROLE_SUDO);
        $prof = $this->createPageUser('ist9201', 'Professor Example', ROLE_PROF);
        $ta = $this->createPageUser('ist9202', 'Taylor Assistant', ROLE_TA);
        $shift1 = $this->createShift(21, 'L1', null);
        $shift2 = $this->createShift(22, 'L2', $prof);

        $oldEntityManager = $this->mockShiftsEntityManager(
            [$shift1, $shift2],
            [$prof, $ta],
            [
                'ist9201' => $prof,
                'ist9202' => $ta,
            ]
        );

        try {
            $this->setUpPageRuntime(
                'shifts',
                $sudo,
                'POST',
                [],
                [
                    'form' => [
                        'shift_21' => 'ist9202',
                        'shift_22' => 'ist9201',
                        'submit' => '',
                    ],
                ]
            );
            $this->runPage('shifts');

            $this->assertSame($ta, $shift1->prof);
            $this->assertSame($prof, $shift2->prof);
            $this->assertSame('Saved!', $GLOBALS['success_message']);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testShiftsPageRejectsUnknownOrNonTaSelection()
    {
        $result = $this->runShiftsScriptWithUnknownUser();

        $this->assertSame(0, $result['exit_code']);
        $this->assertSame('Unknown user', $result['output']);
    }

    private function createShift(int $id, string $name, ?\User $prof): \Shift
    {
        $shift = new \Shift($name, get_current_year());
        $shift->id = $id;
        $shift->prof = $prof;
        return $shift;
    }

    private function mockShiftsEntityManager(array $shifts, array $eligibleUsers, array $usersById): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($shifts, $eligibleUsers, $usersById) {
            public function __construct(
                private array $shifts,
                private array $eligibleUsers,
                private array $usersById
            ) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'User') {
                    return $this->usersById[$id] ?? null;
                }

                return null;
            }

            public function getRepository(string $entity): object
            {
                if ($entity === 'Shift') {
                    return new class($this->shifts) {
                        public function __construct(private array $shifts) {}
                        public function findByYear($year, $order): array
                        {
                            $GLOBALS['__page_test_shifts_year'] = $year;
                            return $this->shifts;
                        }
                    };
                }

                if ($entity === 'User') {
                    return new class($this->eligibleUsers) {
                        public function __construct(private array $users) {}
                        public function findByRole($roles, $order): array
                        {
                            return $this->users;
                        }
                    };
                }

                throw new \RuntimeException("Unexpected repository lookup: $entity");
            }
        };

        return $oldEntityManager;
    }

    private function runShiftsScriptWithUnknownUser(): array
    {
        $script = sys_get_temp_dir() . '/pic1_shifts_' . bin2hex(random_bytes(4)) . '.php';
        $bootstrap = var_export(dirname(__DIR__, 3) . '/tests/bootstrap.php', true);
        $page = var_export(dirname(__DIR__, 3) . '/pages/shifts.php', true);
        $year = get_current_year();

        $code = <<<PHP
<?php
require $bootstrap;
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_GET = ['page' => 'shifts'];
\$_POST = [
    'form' => [
        'shift_31' => 'ist9301',
        'submit' => '',
    ],
];
\$_REQUEST = \$_GET + \$_POST;
\$request = Symfony\Component\HttpFoundation\Request::create('/index.php?page=shifts', 'POST', \$_POST);
\$formFactory = Symfony\Component\Form\Forms::createFormFactoryBuilder()
    ->addExtension(new Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension())
    ->getFormFactory();
\$GLOBALS['entityManager'] = new class {
    public function find(string \$entity, \$id): mixed
    {
        if (\$entity === 'User') {
            return new User('ist9301', 'Student Choice', 'ist9301@example.com', '', ROLE_STUDENT, false);
        }
        return null;
    }

    public function getRepository(string \$entity): object
    {
        if (\$entity === 'Shift') {
            return new class {
                public function findByYear(\$year, \$order): array
                {
                    \$shift = new Shift('T1', $year);
                    \$shift->id = 31;
                    \$shift->prof = null;
                    return [\$shift];
                }
            };
        }

        if (\$entity === 'User') {
            return new class {
                public function findByRole(\$roles, \$order): array
                {
                    return [
                        new User('ist9301', 'Professor Example', 'ist9301@example.com', '', ROLE_PROF, false),
                    ];
                }
            };
        }

        throw new RuntimeException('Unexpected repository lookup');
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

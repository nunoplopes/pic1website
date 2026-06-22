<?php

namespace Tests\Integration\Pages;



class ChangeRolePageTest extends PageTestCase
{
    public function testChangeRolePageUpdatesUserRole()
    {
        $target = $this->createPageUser('ist9940', 'Student Target', ROLE_STUDENT);
        $oldEntityManager = $this->mockChangeRoleEntityManager($target);

        try {
            $prof = $this->createPageUser('ist9941', 'Professor Admin', ROLE_PROF);
            $this->setUpPageRuntime(
                'changerole',
                $prof,
                'POST',
                [],
                [
                    'form' => [
                        'username' => $target->id,
                        'role' => '2',
                        'submit' => '',
                    ],
                ]
            );
            $this->runPage('changerole');

            $this->assertSame(ROLE_TA, $target->role);
            $this->assertSame('Changed role successfully!', $GLOBALS['success_message']);
            $this->assertSame(1, $GLOBALS['entityManager']->flushCount);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    private function mockChangeRoleEntityManager(\User $target): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($target) {
            public int $flushCount = 0;

            public function __construct(private \User $target) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'User' && $id === $this->target->id) {
                    return $this->target;
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

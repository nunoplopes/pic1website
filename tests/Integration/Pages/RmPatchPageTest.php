<?php

namespace Tests\Integration\Pages;

use Tests\Mocks\FakePatch;
require_once dirname(__DIR__, 3) . '/entities/Patch.php';

class RmPatchPageTest extends PageTestCase
{
    public function testRmPatchPageBuildsConfirmationLink()
    {
        $patch = $this->createPatch();
        $oldEntityManager = $this->mockRmPatchEntityManager($patch);

        try {
            $prof = $this->createPageUser('ist9960', 'Professor Remove', ROLE_PROF);
            $this->setUpPageRuntime('rmpatch', $prof, 'GET', ['id' => $patch->id]);
            $this->runPage('rmpatch');

            $prompt = array_key_first($GLOBALS['confirm']);
            $this->assertStringContainsString('delete patch 9301', $prompt);
            $this->assertSame(
                'index.php?id=9301&sure=1&page=rmpatch',
                $GLOBALS['confirm'][$prompt]['url']
            );
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testRmPatchPageDeletesPatchWhenConfirmed()
    {
        $patch = $this->createPatch();
        $oldEntityManager = $this->mockRmPatchEntityManager($patch);

        try {
            $prof = $this->createPageUser('ist9961', 'Professor Remove', ROLE_PROF);
            $this->setUpPageRuntime('rmpatch', $prof, 'GET', ['id' => $patch->id, 'sure' => 1]);
            $this->runPage('rmpatch');

            $this->assertSame('Patch deleted', $GLOBALS['success_message']);
            $this->assertSame($patch, $GLOBALS['entityManager']->removed[0] ?? null);
            $this->assertSame(1, $GLOBALS['entityManager']->flushCount);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    private function createPatch(): \Patch
    {
        $student = $this->createPageUser('ist9962', 'Patch Student', ROLE_STUDENT);
        $shift = new \Shift('T1', get_current_year());
        $group = new \ProjGroup(93, get_current_year(), $shift);
        $group->id = 9300;
        $group->addStudent($student);

        $patch = new FakePatch(
            patchOrigin: 'owner:repo:feature-branch',
            patchText: '',
            branchHash: 'hash',
            computedLinesAdded: 0,
            computedLinesDeleted: 0,
            computedFilesModified: 0,
            patchUrl: 'https://github.com/owner/repo/tree/feature-branch',
            commitUrlBase: ''
        );

        $patch->id = 9301;
        $patch->group = $group;
        $patch->status = \PatchStatus::WaitingReview;
        $patch->type = \PatchType::Feature;
        $patch->lines_added = 0;
        $patch->lines_deleted = 0;
        $patch->files_modified = 0;
        $patch->comments->add(new \PatchComment($patch, 'Initial patch', $student));

        return $patch;
    }

    private function mockRmPatchEntityManager(\Patch $patch): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new class($patch) {
            public array $removed = [];
            public int $flushCount = 0;

            public function __construct(private \Patch $patch) {}

            public function find(string $entity, $id): mixed
            {
                if ($entity === 'Patch' && $id === $this->patch->id) {
                    return $this->patch;
                }

                return null;
            }

            public function remove($obj): void
            {
                $this->removed[] = $obj;
            }

            public function flush(): void
            {
                $this->flushCount++;
            }
        };

        return $oldEntityManager;
    }
}

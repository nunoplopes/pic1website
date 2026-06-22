<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;
use Tests\Mocks\FakeQueryResultEntityManager;



/**
 * Test suite for ProjGroup entity
 * 
 * Tests project group properties, repository information, and project details
 */
class ProjGroupTest extends UnitTestCase
{
    private \ProjGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $shift = new \Shift('T01', 2024);
        $this->group = new \ProjGroup(1, 2024, $shift);
    }

    /**
     * Test project group can be created
     */
    public function testProjGroupCanBeCreated()
    {
        $this->assertInstanceOf(\ProjGroup::class, $this->group);
    }

    /**
     * Test group number can be set and retrieved
     */
    public function testGroupNumberCanBeSetAndRetrieved()
    {
        $this->assertEquals(1, $this->group->group_number);
        
        $this->group->group_number = 5;
        $this->assertEquals(5, $this->group->group_number);
    }

    /**
     * Test year can be set and retrieved
     */
    public function testYearCanBeSetAndRetrieved()
    {
        $this->assertEquals(2024, $this->group->year);
        
        $this->group->year = 2025;
        $this->assertEquals(2025, $this->group->year);
    }

    /**
     * Test project name can be set and retrieved
     */
    public function testProjectNameCanBeSetAndRetrieved()
    {
        $projectName = 'Example Project';
        $this->group->project_name = $projectName;
        
        $this->assertEquals($projectName, $this->group->project_name);
    }

    /**
     * Test project description can be set and retrieved
     */
    public function testProjectDescriptionCanBeSetAndRetrieved()
    {
        $description = 'This is a comprehensive description of the project';
        $this->group->project_description = $description;
        
        $this->assertEquals($description, $this->group->project_description);
    }

    /**
     * Test project website can be set and retrieved
     */
    public function testProjectWebsiteCanBeSetAndRetrieved()
    {
        $website = 'https://example-project.org';
        $this->group->project_website = $website;
        
        $this->assertEquals($website, $this->group->project_website);
    }

    /**
     * Test repository URL can be set and retrieved
     */
    public function testRepositoryCanBeSetAndRetrieved()
    {
        $repo = 'https://github.com/example/project';
        $this->group->repository = $repo;
        
        $this->assertEquals($repo, $this->group->repository);
    }

    /**
     * Test CLA flag can be set and retrieved
     */
    public function testCLAFlagCanBeSetAndRetrieved()
    {
        $this->assertFalse($this->group->cla);
        
        $this->group->cla = true;
        $this->assertTrue($this->group->cla);
    }

    /**
     * Test DCO flag can be set and retrieved
     */
    public function testDCOFlagCanBeSetAndRetrieved()
    {
        $this->assertFalse($this->group->dco);
        
        $this->group->dco = true;
        $this->assertTrue($this->group->dco);
    }

    /**
     * Test major users can be set and retrieved
     */
    public function testMajorUsersCanBeSetAndRetrieved()
    {
        $majorUsers = 'Apache, OpenStack, Google';
        $this->group->major_users = $majorUsers;
        
        $this->assertEquals($majorUsers, $this->group->major_users);
    }

    /**
     * Test coding style link can be set and retrieved
     */
    public function testCodingStyleLinkCanBeSetAndRetrieved()
    {
        $link = 'https://example.org/coding-style';
        $this->group->coding_style = $link;
        
        $this->assertEquals($link, $this->group->coding_style);
    }

    /**
     * Test bugs for beginners link can be set and retrieved
     */
    public function testBugsForBeginnersLinkCanBeSetAndRetrieved()
    {
        $link = 'https://example.org/issues?label=beginner';
        $this->group->bugs_for_beginners = $link;
        
        $this->assertEquals($link, $this->group->bugs_for_beginners);
    }

    /**
     * Test project ideas link can be set and retrieved
     */
    public function testProjectIdeasLinkCanBeSetAndRetrieved()
    {
        $link = 'https://example.org/ideas';
        $this->group->project_ideas = $link;
        
        $this->assertEquals($link, $this->group->project_ideas);
    }

    /**
     * Test student programs can be set and retrieved
     */
    public function testStudentProgramsCanBeSetAndRetrieved()
    {
        $programs = 'GSoC, Outreachy, LFX';
        $this->group->student_programs = $programs;
        
        $this->assertEquals($programs, $this->group->student_programs);
    }

    /**
     * Test getting started manual link can be set and retrieved
     */
    public function testGettingStartedManualLinkCanBeSetAndRetrieved()
    {
        $link = 'https://example.org/getting-started';
        $this->group->getting_started_manual = $link;
        
        $this->assertEquals($link, $this->group->getting_started_manual);
    }

    /**
     * Test developers manual link can be set and retrieved
     */
    public function testDevelopersManualLinkCanBeSetAndRetrieved()
    {
        $link = 'https://example.org/developers';
        $this->group->developers_manual = $link;
        
        $this->assertEquals($link, $this->group->developers_manual);
    }

    /**
     * Test testing manual link can be set and retrieved
     */
    public function testTestingManualLinkCanBeSetAndRetrieved()
    {
        $link = 'https://example.org/testing';
        $this->group->testing_manual = $link;
        
        $this->assertEquals($link, $this->group->testing_manual);
    }

    /**
     * Test developers mailing list can be set and retrieved
     */
    public function testDeveloperMailingListCanBeSetAndRetrieved()
    {
        $list = 'https://example.org/mailing-list';
        $this->group->developers_mailing_list = $list;
        
        $this->assertEquals($list, $this->group->developers_mailing_list);
    }

    /**
     * Test patch submission link can be set and retrieved
     */
    public function testPatchSubmissionLinkCanBeSetAndRetrieved()
    {
        $link = 'https://example.org/patch-submission';
        $this->group->patch_submission = $link;
        
        $this->assertEquals($link, $this->group->patch_submission);
    }

    /**
     * Test hash proposal file can be set and retrieved
     */
    public function testHashProposalFileCanBeSetAndRetrieved()
    {
        $hash = 'abc123def456ghi789';
        $this->group->hash_proposal_file = $hash;
        
        $this->assertEquals($hash, $this->group->hash_proposal_file);
    }

    /**
     * Test default project website value
     */
    public function testDefaultProjectWebsite()
    {
        $shift = new \Shift('T02', 2024);
        $newGroup = new \ProjGroup(2, 2024, $shift);
        $this->assertEquals('https://example.org', $newGroup->project_website);
    }

    /**
     * Test default links
     */
    public function testDefaultLinks()
    {
        $shift = new \Shift('T03', 2024);
        $newGroup = new \ProjGroup(3, 2024, $shift);
        $this->assertEquals('https://example.org', $newGroup->coding_style);
        $this->assertEquals('https://example.org', $newGroup->bugs_for_beginners);
        $this->assertEquals('https://example.org', $newGroup->project_ideas);
        $this->assertEquals('https://example.org', $newGroup->getting_started_manual);
        $this->assertEquals('https://example.org', $newGroup->developers_manual);
        $this->assertEquals('https://example.org', $newGroup->testing_manual);
        $this->assertEquals('https://example.org', $newGroup->patch_submission);
    }

    public function testResetStudentsRemovesGroupFromStudents()
    {
        $student1 = new \User('ist10001', 'Test Student One', 'ist10001@tecnico.ulisboa.pt', '', ROLE_STUDENT, false);
        $student2 = new \User('ist10002', 'Test Student Two', 'ist10002@tecnico.ulisboa.pt', '', ROLE_STUDENT, false);

        $this->group->addStudent($student1);
        $this->group->addStudent($student2);

        $this->group->resetStudents();

        $this->assertTrue($this->group->students->isEmpty());
        $this->assertFalse($student1->groups->contains($this->group));
        $this->assertFalse($student2->groups->contains($this->group));
    }

    public function testRepositoryHelpersReturnRepositoryData()
    {
        $this->group->repository = 'github:mockorg/mockrepo';

        $repository = $this->group->getRepository();

        $this->assertInstanceOf(\Repository::class, $repository);
        $this->assertEquals('github:mockorg/mockrepo', $this->group->getRepositoryId());
        $this->assertEquals('github:mockorg/mockrepo', $this->group->getValidRepository()?->id);
        $this->assertEquals('https://github.com/mockorg/mockrepo', $this->group->getstr_repository());
    }

    public function testRepositoryHelpersHandleEmptyRepository()
    {
        $this->group->repository = '';

        $this->assertNull($this->group->getRepository());
        $this->assertEquals('', $this->group->getRepositoryId());
        $this->assertNull($this->group->getValidRepository());
        $this->assertEquals('', $this->group->getstr_repository());
    }

    public function testProfReturnsShiftProfessor()
    {
        $prof = new \User('prof', 'Professor User', 'prof@example.com', '', ROLE_PROF, false);
        $this->group->shift->prof = $prof;

        $this->assertSame($prof, $this->group->prof());
    }

    public function testToStringReturnsGroupNumber()
    {
        $this->assertEquals('1', (string)$this->group);
    }

    public function testSetRepositoryStoresParsedRepository()
    {
        $oldEntityManager = $this->mockGroupsByRepo([]);
        $this->mockGitHubClient();

        try {
            $this->group->set_repository('https://github.com/mockorg/mockrepo');
            $this->assertEquals('github:mockorg/mockrepo', $this->group->repository);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testSetRepositoryCanClearRepository()
    {
        $this->group->repository = 'github:mockorg/mockrepo';

        $this->group->set_repository('');

        $this->assertEquals('', $this->group->repository);
    }

    public function testSetRepositoryDoesNotCheckLimitWhenRepositoryUnchanged()
    {
        $this->group->repository = 'github:mockorg/mockrepo';
        $oldEntityManager = $this->mockGroupsByRepo(array_fill(0, 5, ['id' => 1]));
        $this->mockGitHubClient();

        try {
            $this->group->set_repository('https://github.com/mockorg/mockrepo');
            $this->assertEquals('github:mockorg/mockrepo', $this->group->repository);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testSetRepositoryThrowsWhenRepositoryAlreadyHasFiveGroups()
    {
        $oldEntityManager = $this->mockGroupsByRepo(array_fill(0, 5, ['id' => 1]));
        $this->mockGitHubClient();

        try {
            $this->expectException(\ValidationException::class);
            $this->expectExceptionMessage('Exceed the maximum number of groups per repository');
            $this->group->set_repository('https://github.com/mockorg/mockrepo');
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testUrlSettersStoreValidUrls()
    {
        foreach ($this->urlSetterCases() as $setter => $property) {
            $url = "https://example.org/$property";

            $this->group->$setter($url);

            $this->assertEquals($url, $this->group->$property);
        }
    }

    public function testUrlSettersCanClearUrls()
    {
        foreach ($this->urlSetterCases() as $setter => $property) {
            $this->group->$property = "https://example.org/$property";

            $this->group->$setter('');

            $this->assertEquals('', $this->group->$property);
        }
    }

    public function testUrlSettersRejectMalformedUrls()
    {
        foreach ($this->urlSetterCases() as $setter => $property) {
            $previous = $this->group->$property;

            try {
                $this->group->$setter('not-a-url');
                $this->fail("$setter should reject malformed URLs");
            } catch (\ValidationException $exception) {
                $this->assertEquals('Malformed URL', $exception->getMessage());
                $this->assertEquals($previous, $this->group->$property);
            }
        }
    }

    private function urlSetterCases(): array
    {
        return [
            'set_project_website' => 'project_website',
            'set_coding_style' => 'coding_style',
            'set_bugs_for_beginners' => 'bugs_for_beginners',
            'set_project_ideas' => 'project_ideas',
            'set_getting_started_manual' => 'getting_started_manual',
            'set_developers_manual' => 'developers_manual',
            'set_testing_manual' => 'testing_manual',
            'set_developers_mailing_list' => 'developers_mailing_list',
            'set_patch_submission' => 'patch_submission',
        ];
    }

    private function mockGitHubClient(): void
    {
        $this->replaceCachedGitHubClient(new class {
            public function api($endpoint) {
                return new class {
                    public function show($owner, $repo) {
                        return [
                            'full_name' => "$owner/$repo",
                            'default_branch' => 'main',
                        ];
                    }
                };
            }
        });
    }

    private function mockGroupsByRepo(array $groups): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new FakeQueryResultEntityManager($groups);
        return $oldEntityManager;
    }
}

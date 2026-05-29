<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;
use Tests\Mocks\FakeQueryResultEntityManager;
use ValidationException;

/**
 * Test suite for SelectedBug entity
 * 
 * Tests selected bug creation, properties, and structure
 */
class SelectedBugTest extends UnitTestCase
{
    private \User $testUser;
    private \ProjGroup $testGroup;
    private \Shift $testShift;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test shift
        $this->testShift = new \Shift('T01', 2024);

        // Create test group
        $this->testGroup = new \ProjGroup(1, 2024, $this->testShift);

        // Create test user
        $this->testUser = new \User(
            username: 'bugfinder',
            name: 'Bug Finder',
            email: 'bug@example.com',
            photo: '',
            role: ROLE_STUDENT,
            dummy: false
        );
    }

    /**
     * Test selected bug can be instantiated
     */
    public function testSelectedBugCanBeInstantiated()
    {
        $bug = new \SelectedBug();
        $this->assertInstanceOf(\SelectedBug::class, $bug);
    }

    /**
     * Test selected bug has year property
     */
    public function testSelectedBugHasYearProperty()
    {
        $bug = new \SelectedBug();
        $bug->year = 2024;
        $this->assertEquals(2024, $bug->year);
    }

    /**
     * Test selected bug has issue_url property
     */
    public function testSelectedBugHasIssueUrlProperty()
    {
        $bug = new \SelectedBug();
        $bug->issue_url = 'https://github.com/owner/repo/issues/1';
        $this->assertEquals('https://github.com/owner/repo/issues/1', $bug->issue_url);
    }

    /**
     * Test selected bug has repro_url property
     */
    public function testSelectedBugHasReproUrlProperty()
    {
        $bug = new \SelectedBug();
        $bug->repro_url = 'https://youtube.com/watch?v=test';
        $this->assertEquals('https://youtube.com/watch?v=test', $bug->repro_url);
    }

    /**
     * Test selected bug has description property
     */
    public function testSelectedBugHasDescriptionProperty()
    {
        $bug = new \SelectedBug();
        $bug->description = 'Test bug description';
        $this->assertEquals('Test bug description', $bug->description);
    }

    /**
     * Test selected bug has user property
     */
    public function testSelectedBugHasUserProperty()
    {
        $bug = new \SelectedBug();
        $bug->user = $this->testUser;
        $this->assertEquals($this->testUser, $bug->user);
    }

    /**
     * Test set_issue_url requires non-empty URL
     */
    public function testSetIssueUrlRequiresNonEmptyUrl()
    {
        $bug = new \SelectedBug();
        $bug->year = 2024;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Issue URL is required');

        $bug->set_issue_url('');
    }

    /**
     * Test set_repro_url accepts empty URL
     */
    public function testSetReproUrlAcceptsEmptyUrl()
    {
        $bug = new \SelectedBug();
        
        // Empty URL should not trigger video validation
        $bug->set_repro_url('');
        $this->assertEquals('', $bug->repro_url);
    }

    public function testSetReproUrlRejectsUnrecognizedVideoUrl()
    {
        $bug = new \SelectedBug();

        $this->expectException(ValidationException::class);

        $bug->set_repro_url('https://example.invalid/not-a-video');
    }

    /**
     * Test issue_url defaults to empty string
     */
    public function testIssueUrlDefaultsToEmpty()
    {
        $bug = new \SelectedBug();
        $this->assertEquals('', $bug->issue_url);
    }

    /**
     * Test repro_url defaults to empty string
     */
    public function testReproUrlDefaultsToEmpty()
    {
        $bug = new \SelectedBug();
        $this->assertEquals('', $bug->repro_url);
    }

    /**
     * Test description defaults to empty string
     */
    public function testDescriptionDefaultsToEmpty()
    {
        $bug = new \SelectedBug();
        $this->assertEquals('', $bug->description);
    }

    /**
     * Test multiple bugs can have different years
     */
    public function testMultipleBugsDifferentYears()
    {
        $bug1 = new \SelectedBug();
        $bug1->year = 2024;
        
        $bug2 = new \SelectedBug();
        $bug2->year = 2025;
        
        $this->assertNotEquals($bug1->year, $bug2->year);
    }

    /**
     * Test bug can have different URLs set
     */
    public function testBugCanHaveDifferentUrls()
    {
        $bug = new \SelectedBug();
        
        $bug->issue_url = 'https://github.com/owner/repo/issues/1';
        $this->assertEquals('https://github.com/owner/repo/issues/1', $bug->issue_url);
        
        $bug->issue_url = 'https://github.com/owner/repo/issues/2';
        $this->assertEquals('https://github.com/owner/repo/issues/2', $bug->issue_url);
    }

    /**
     * Test description can be updated
     */
    public function testDescriptionCanBeUpdated()
    {
        $bug = new \SelectedBug();
        
        $bug->description = 'First description';
        $this->assertEquals('First description', $bug->description);
        
        $bug->description = 'Updated description';
        $this->assertEquals('Updated description', $bug->description);
    }

    /**
     * Test bug user relationship
     */
    public function testBugUserRelationship()
    {
        $bug1 = new \SelectedBug();
        $bug1->user = $this->testUser;
        
        $user2 = new \User(
            username: 'user2',
            name: 'User Two',
            email: 'user2@example.com',
            photo: '',
            role: ROLE_STUDENT,
            dummy: false
        );
        $bug2 = new \SelectedBug();
        $bug2->user = $user2;
        
        $this->assertNotEquals($bug1->user, $bug2->user);
    }

    public function testFactoryCreatesSelectedBugWithValidData()
    {
        $oldEntityManager = $this->mockExistingBug(null);

        try {
            $bug = \SelectedBug::factory(
                $this->testGroup,
                $this->testUser,
                '  Detailed bug description  ',
                'https://github.com/owner/repo/issues/123',
                ''
            );

            $this->assertEquals(2024, $bug->year);
            $this->assertSame($this->testUser, $bug->user);
            $this->assertEquals('Detailed bug description', $bug->description);
            $this->assertEquals('https://github.com/owner/repo/issues/123', $bug->issue_url);
            $this->assertEquals('', $bug->repro_url);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testSetIssueUrlStoresValidUrl()
    {
        $oldEntityManager = $this->mockExistingBug(null);
        $bug = new \SelectedBug();
        $bug->year = 2024;

        try {
            $bug->set_issue_url('https://github.com/owner/repo/issues/123');
            $this->assertEquals('https://github.com/owner/repo/issues/123', $bug->issue_url);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testSetIssueUrlThrowsWhenBugAlreadySelected()
    {
        $existingBug = new \SelectedBug();
        $existingBug->year = 2024;
        $existingBug->issue_url = 'https://github.com/owner/repo/issues/123';
        $oldEntityManager = $this->mockExistingBug($existingBug);

        $bug = new \SelectedBug();
        $bug->year = 2024;

        try {
            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage('This bug has been selected by another student already');
            $bug->set_issue_url('https://github.com/owner/repo/issues/123');
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testSetIssueUrlSkipsDuplicateCheckForUnchangedUrl()
    {
        $existingBug = new \SelectedBug();
        $existingBug->year = 2024;
        $existingBug->issue_url = 'https://github.com/owner/repo/issues/123';
        $oldEntityManager = $this->mockExistingBug($existingBug);

        $bug = new \SelectedBug();
        $bug->year = 2024;
        $bug->issue_url = 'https://github.com/owner/repo/issues/123';

        try {
            $bug->set_issue_url('https://github.com/owner/repo/issues/123');
            $this->assertEquals('https://github.com/owner/repo/issues/123', $bug->issue_url);
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    public function testSetIssueUrlRejectsMalformedUrl()
    {
        $oldEntityManager = $this->mockExistingBug(null);
        $bug = new \SelectedBug();
        $bug->year = 2024;

        try {
            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage('Malformed URL');
            $bug->set_issue_url('not-a-url');
        } finally {
            $GLOBALS['entityManager'] = $oldEntityManager;
        }
    }

    private function mockExistingBug(?\SelectedBug $existingBug): mixed
    {
        $oldEntityManager = $GLOBALS['entityManager'] ?? null;
        $GLOBALS['entityManager'] = new FakeQueryResultEntityManager($existingBug);

        return $oldEntityManager;
    }
}

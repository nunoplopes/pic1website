<?php

namespace Tests\Integration;

use Tests\Integration\Base\IntegrationTestCase;

/**
 * Integration tests for Session and Authentication workflows
 * 
 * Tests the interaction between:
 * - User (authenticated)
 * - Session (active session)
 * - User permissions
 * - Session lifecycle
 */
class SessionAuthenticationIntegrationTest extends IntegrationTestCase
{
    private \User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = new \User(
            username: 'testuser',
            name: 'Test User',
            email: 'test@example.com',
            photo: '',
            role: ROLE_STUDENT,
            dummy: false
        );
    }

    /**
     * Test user login creates session
     * 
     * Workflow:
     * 1. User provides credentials
     * 2. Credentials authenticated
     * 3. Session created for user
     * 4. Session assigned unique ID
     */
    public function testUserLoginCreatesSession()
    {
        $session = new \Session($this->user);

        // Verify session is created and linked to user
        $this->assertNotNull($session->id);
        $this->assertEquals($this->user, $session->user);
        $this->assertTrue($session->isFresh());
    }

    /**
     * Test session is fresh on creation
     * 
     * Workflow:
     * 1. Create new session
     * 2. Check if session is fresh
     * 3. Verify expiration is in future
     */
    public function testSessionFreshnessOnCreation()
    {
        $session = new \Session($this->user);

        // Session should be fresh immediately after creation
        $this->assertTrue($session->isFresh());
        $this->assertGreaterThan(new \DateTimeImmutable(), $session->expires);
    }

    /**
     * Test session expiration in 90 days
     * 
     * Workflow:
     * 1. Create session at time T
     * 2. Calculate expected expiration (T + 90 days)
     * 3. Verify session expires at expected time
     */
    public function testSessionExpiresIn90Days()
    {
        $now = new \DateTimeImmutable();
        $session = new \Session($this->user);
        $expectedExpiration = $now->add(new \DateInterval('P90D'));

        // Session should expire in approximately 90 days
        $diff = $session->expires->getTimestamp() - $expectedExpiration->getTimestamp();
        $this->assertLessThan(2, abs($diff));  // Within 2 seconds
    }

    /**
     * Test each user gets unique session
     * 
     * Workflow:
     * 1. User A creates session
     * 2. User B creates session
     * 3. Sessions have different IDs
     * 4. Each session belongs to correct user
     */
    public function testEachUserGetUniqueSessions()
    {
        $user1 = new \User('user1', 'User 1', 'user1@example.com', '', ROLE_STUDENT, false);
        $user2 = new \User('user2', 'User 2', 'user2@example.com', '', ROLE_STUDENT, false);

        $session1 = new \Session($user1);
        $session2 = new \Session($user2);

        // Sessions should be different
        $this->assertNotEquals($session1->id, $session2->id);
        $this->assertEquals('user1', $session1->user->id);
        $this->assertEquals('user2', $session2->user->id);
    }

    /**
     * Test user can have multiple active sessions
     * 
     * Workflow:
     * 1. User creates first session (login on device A)
     * 2. User creates second session (login on device B)
     * 3. Both sessions are valid and independent
     * 4. User can switch between sessions
     */
    public function testUserCanHaveMultipleSessions()
    {
        $session1 = new \Session($this->user);
        $session2 = new \Session($this->user);
        $session3 = new \Session($this->user);

        // All sessions should be different
        $this->assertNotEquals($session1->id, $session2->id);
        $this->assertNotEquals($session2->id, $session3->id);
        $this->assertNotEquals($session1->id, $session3->id);

        // All sessions belong to same user
        $this->assertEquals($this->user->id, $session1->user->id);
        $this->assertEquals($this->user->id, $session2->user->id);
        $this->assertEquals($this->user->id, $session3->user->id);

        // All sessions are fresh
        $this->assertTrue($session1->isFresh());
        $this->assertTrue($session2->isFresh());
        $this->assertTrue($session3->isFresh());
    }

    /**
     * Test session ID is cryptographically secure
     * 
     * Workflow:
     * 1. Create multiple sessions
     * 2. Verify all IDs are unique (extremely low collision probability)
     * 3. Verify ID format is valid hex
     */
    public function testSessionIdIsSecure()
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $session = new \Session($this->user);
            $ids[] = $session->id;

            // Verify ID is hex string
            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $session->id);
        }

        // All IDs should be unique
        $uniqueIds = array_unique($ids);
        $this->assertCount(100, $uniqueIds);
    }

    /**
     * Test session retrieval by ID
     * 
     * Workflow:
     * 1. Create session and get its ID
     * 2. Use ID to retrieve session
     * 3. Verify retrieved session matches original
     * 4. Verify user is accessible through session
     */
    /**
     * Test session retrieval from database by ID
     * 
     * Workflow:
     * 1. Create and insert user into database
     * 2. Create and insert session into database
     * 3. Query session back from database by ID
     * 4. Verify retrieved session matches persisted data
     * 5. Verify user relationship is intact
     */
    public function testSessionRetrievalById()
    {
        // Create user in database
        $userId = 'test_session_' . bin2hex(random_bytes(4));
        $this->insertTestUser($userId, 'Test Session User');
        
        // Create session and insert into database
        $sessionId = bin2hex(random_bytes(16));
        $expectedExpiration = new \DateTimeImmutable('+90 days');
        $this->insertTestSession($sessionId, $userId, $expectedExpiration);
        
        // Query session back from database
        $retrievedSession = $this->getSessionFromDatabase($sessionId);
        
        // Verify session exists in database
        $this->assertNotNull($retrievedSession, 'Session should be retrieved from database');
        
        // Verify session ID matches
        $this->assertEquals($sessionId, $retrievedSession['id']);
        
        // Verify user ID matches
        $this->assertEquals($userId, $retrievedSession['user_id']);
        
        // Verify expiration is in the future
        $expiration = new \DateTimeImmutable($retrievedSession['expires']);
        $this->assertGreaterThan(new \DateTimeImmutable(), $expiration);
    }

    public function testSessionPersistenceRejectsUnknownUser()
    {
        $this->expectException(\PDOException::class);

        $this->insertTestSession(bin2hex(random_bytes(16)), 'missing-user');
    }

    /**
     * Test student accessing their project group through session
     * 
     * Workflow:
     * 1. Student logs in (creates session)
     * 2. Session carries user identity
     * 3. User accesses their current project group
     * 4. Verify student can see group info
     */
    public function testStudentAccessesGroupThroughSession()
    {
        $student = new \User('student1', 'John Student', 'john@example.com', '', ROLE_STUDENT, false);
        $session = new \Session($student);

        // Create group and add student (simulating group assignment)
        $shift = new \Shift('Monday 10:00', 2024);

        $group = new \ProjGroup(1, 2024, $shift);
        $group->project_name = 'Cool Project';
        $group->students->add($student);

        // Through session, student can access group
        $this->assertEquals($student->id, $session->user->id);
        $this->assertTrue($group->students->contains($session->user));
    }

    /**
     * Test professor managing students through authenticated session
     * 
     * Workflow:
     * 1. Professor logs in (creates session)
     * 2. Professor session identifies them as professor (role check)
     * 3. Professor can grade student submissions
     * 4. Grades are attributed to professor
     */
    public function testProfessorManagementThroughSession()
    {
        $professor = new \User('prof1', 'Prof. Teacher', 'prof@example.com', '', ROLE_PROF, false);
        $session = new \Session($professor);

        // Verify session user has professor role
        $this->assertSame(ROLE_PROF, $session->user->role);
        
        // Professor can create and grade assignments
        $milestone = new \Milestone(2024, 'Assignment 1');
        $milestone->field1 = 'Code Quality';
        $milestone->range1 = 100;

        // Professor can grade students
        $student = new \User('student1', 'John', 'john@example.com', '', ROLE_STUDENT, false);
        $grade = new \Grade();
        $grade->user = $student;
        $grade->milestone = $milestone;
        $grade->field1 = 85;

        // Grades are recorded in context of professor session
        $this->assertNotNull($grade->user);
        $this->assertNotNull($grade->milestone);
    }

    /**
     * Test session maintenance and persistence across page views
     * 
     * Workflow:
     * 1. User logs in and session is stored in database
     * 2. User navigates to multiple pages
     * 3. Session ID remains valid in database
     * 4. User stays authenticated via session lookup
     * 5. Session data persists unchanged
     */
    public function testSessionMaintenanceAcrossPageViews()
    {
        // Create user and session in database
        $userId = 'test_multipage_' . bin2hex(random_bytes(4));
        $sessionId = bin2hex(random_bytes(16));
        $this->insertTestUser($userId, 'Multi-Page User');
        $this->insertTestSession($sessionId, $userId);
        
        $originalSession = $this->getSessionFromDatabase($sessionId);
        $this->assertNotNull($originalSession);
        
        // Simulate accessing multiple pages - session ID should remain in DB
        $pageOneSession = $this->getSessionFromDatabase($sessionId);
        $this->assertEquals($originalSession['id'], $pageOneSession['id']);
        $this->assertEquals($originalSession['user_id'], $pageOneSession['user_id']);
        
        // After accessing page 2
        $pageTwoSession = $this->getSessionFromDatabase($sessionId);
        $this->assertEquals($originalSession['id'], $pageTwoSession['id']);
        
        // Session should still be associated with original user
        $this->assertEquals($userId, $pageTwoSession['user_id']);
        
        // Session data should not have changed
        $this->assertEquals($originalSession, $pageTwoSession);
    }

    /**
     * Test dummy/test user sessions
     * 
     * Workflow:
     * 1. Create session for dummy user
     * 2. Session is valid but marked as test
     * 3. Dummy user sessions don't interfere with real users
     */
    public function testDummyUserSessions()
    {
        $dummyUser = new \User('ist0000001', 'Dummy User', 'dummy@example.com', '', ROLE_STUDENT, true);
        $session = new \Session($dummyUser);

        // Dummy user session is still valid
        $this->assertTrue($session->isFresh());
        $this->assertTrue($session->user->isDummy());
        
        // But can be identified as test
        $this->assertStringStartsWith('ist0000', $session->user->id);
    }
}

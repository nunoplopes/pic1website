<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;



/**
 * Test suite for Session entity
 * 
 * Tests session creation, expiration, and validation logic
 */
class SessionTest extends UnitTestCase
{
    private \User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user for session
        $this->testUser = new \User(
            username: 'testuser',
            name: 'Test User',
            email: 'test@example.com',
            photo: '',
            role: ROLE_STUDENT,
            dummy: false
        );
    }

    /**
     * Test that session can be created with a user
     */
    public function testSessionCanBeCreatedWithUser()
    {
        $session = new \Session($this->testUser);
        
        $this->assertInstanceOf(\Session::class, $session);
        $this->assertSame($this->testUser, $session->user);
    }

    /**
     * Test that session has an ID
     */
    public function testSessionHasAnId()
    {
        $session = new \Session($this->testUser);
        
        $this->assertNotNull($session->id);
        $this->assertIsString($session->id);
        $this->assertNotEmpty($session->id);
    }

    /**
     * Test that session ID is of expected length (SHA1 substring)
     */
    public function testSessionIdIsCorrectLength()
    {
        $session = new \Session($this->testUser);
        
        // SHA1 produces 40 chars, we take first 32
        $this->assertEquals(32, strlen($session->id));
    }

    /**
     * Test that different sessions have different IDs
     */
    public function testDifferentSessionsHaveDifferentIds()
    {
        $session1 = new \Session($this->testUser);
        $session2 = new \Session($this->testUser);
        
        $this->assertNotEquals($session1->id, $session2->id);
    }

    /**
     * Test that session has an expiration time
     */
    public function testSessionHasExpirationTime()
    {
        $session = new \Session($this->testUser);
        
        $this->assertNotNull($session->expires);
        $this->assertInstanceOf(\DateTimeImmutable::class, $session->expires);
    }

    /**
     * Test that session expires in approximately 90 days
     */
    public function testSessionExpiresIn90Days()
    {
        $beforeCreation = new \DateTimeImmutable();
        $session = new \Session($this->testUser);
        $afterCreation = new \DateTimeImmutable();

        // Add 90 days to current time
        $expectedExpiration = $beforeCreation->add(new \DateInterval("P90D"));
        $expectedExpirationAfter = $afterCreation->add(new \DateInterval("P90D"));

        // Session expiration should be between the two times
        $this->assertGreaterThanOrEqual($expectedExpiration, $session->expires);
        $this->assertLessThanOrEqual($expectedExpirationAfter, $session->expires);
    }

    /**
     * Test that new session is fresh
     */
    public function testNewSessionIsFresh()
    {
        $session = new \Session($this->testUser);
        
        $this->assertTrue($session->isFresh());
    }

    /**
     * Test that session user is set correctly
     */
    public function testSessionUserIsSetCorrectly()
    {
        $session = new \Session($this->testUser);
        
        $this->assertEquals('testuser', $session->user->id);
        $this->assertEquals('Test User', $session->user->name);
        $this->assertEquals('test@example.com', $session->user->email);
    }

    /**
     * Test that session can be created for different users
     */
    public function testSessionCanBeCreatedForDifferentUsers()
    {
        $user1 = new \User('user1', 'User One', 'user1@example.com', '', ROLE_STUDENT, false);
        $user2 = new \User('user2', 'User Two', 'user2@example.com', '', ROLE_STUDENT, false);

        $session1 = new \Session($user1);
        $session2 = new \Session($user2);

        $this->assertEquals('user1', $session1->user->id);
        $this->assertEquals('user2', $session2->user->id);
        $this->assertNotEquals($session1->id, $session2->id);
    }

    /**
     * Test that session ID consists of valid characters (hex)
     */
    public function testSessionIdIsValidHex()
    {
        $session = new \Session($this->testUser);
        
        // Session ID should be hexadecimal (0-9, a-f)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $session->id);
    }

    /**
     * Test that session expiration is in the future
     */
    public function testSessionExpirationIsInFuture()
    {
        $session = new \Session($this->testUser);
        $now = new \DateTimeImmutable();
        
        $this->assertGreaterThan($now, $session->expires);
    }

    /**
     * Test that multiple sessions from same user have different IDs
     */
    public function testMultipleSessionsFromSameUserHaveDifferentIds()
    {
        $ids = [];
        
        for ($i = 0; $i < 5; $i++) {
            $session = new \Session($this->testUser);
            $ids[] = $session->id;
        }

        // All IDs should be unique
        $this->assertCount(5, array_unique($ids));
    }

    /**
     * Test session ID can be used as string
     */
    public function testSessionIdCanBeUsedAsString()
    {
        $session = new \Session($this->testUser);
        
        $id = $session->id;
        $this->assertIsString($id);
        $this->assertTrue(strlen($id) > 0);
        
        // Can be used in string context
        $message = "Session: $id";
        $this->assertStringContainsString($session->id, $message);
    }

    /**
     * Test that session objects are separate instances
     */
    public function testSessionObjectsAreSeparateInstances()
    {
        $session1 = new \Session($this->testUser);
        $session2 = new \Session($this->testUser);
        
        $this->assertNotSame($session1, $session2);
    }
}

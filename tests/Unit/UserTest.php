<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;



/**
 * Test suite for User entity
 * 
 * Tests the User class properties, methods, and business logic
 */
class UserTest extends UnitTestCase
{
    private \User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = new \User(
            username: 'joedoe',
            name: 'Joe Smith Doe',
            email: 'joe@example.com',
            photo: 'https://example.com/photo.jpg',
            role: ROLE_PROF,
            dummy: false
        );
    }

    /**
     * Test that user can be instantiated with correct properties
     */
    public function testUserCanBeCreated()
    {
        $this->assertInstanceOf(\User::class, $this->user);
        $this->assertEquals('joedoe', $this->user->id);
        $this->assertEquals('Joe Smith Doe', $this->user->name);
        $this->assertEquals('joe@example.com', $this->user->email);
    }

    /**
     * Test that user properties can be accessed
     */
    public function testUserPropertiesAreAccessible()
    {
        $this->assertEquals('joedoe', $this->user->id);
        $this->assertEquals('Joe Smith Doe', $this->user->name);
        $this->assertEquals('joe@example.com', $this->user->email);
        $this->assertEquals('https://example.com/photo.jpg', $this->user->photo);
        $this->assertEquals(ROLE_PROF, $this->user->role);
    }

    /**
     * Test shortName() method returns first and last name
     */
    public function testShortNameReturnsFirstAndLastName()
    {
        $shortName = $this->user->shortName();
        $this->assertStringStartsWith('Joe', $shortName);
        $this->assertStringEndsWith('Doe', $shortName);
    }

    /**
     * Test shortName() with single name
     */
    public function testShortNameWithSingleName()
    {
        $user = new \User('user', 'Madonna', 'madonna@example.com', '', ROLE_STUDENT, false);
        $shortName = $user->shortName();
        $this->assertStringContainsString('Madonna', $shortName);
    }

    /**
     * Test shortName() with multiple name parts
     */
    public function testShortNameWithMultipleNameParts()
    {
        $user = new \User('user', 'Jean Claude Van Damme', 'jcvd@example.com', '', ROLE_STUDENT, false);
        $shortName = $user->shortName();
        $this->assertStringStartsWith('Jean', $shortName);
        $this->assertStringEndsWith('Damme', $shortName);
    }

    /**
     * Test isDummy() returns true for dummy users
     */
    public function testIsDummyReturnsTrueForDummyUsers()
    {
        $dummyUser = new \User('ist0000001', 'Dummy User', 'dummy@example.com', '', ROLE_STUDENT, true);
        $this->assertTrue($dummyUser->isDummy());
    }

    /**
     * Test isDummy() returns false for regular users
     */
    public function testIsDummyReturnsFalseForRegularUsers()
    {
        $this->assertFalse($this->user->isDummy());
    }

    /**
     * Test isDummy() detects various dummy ID patterns
     */
    public function testIsDummyDetectsDummyIdPatterns()
    {
        $patterns = [
            'ist0000000',
            'ist0000001',
            'ist0000999',
            'ist0000abc',
        ];

        foreach ($patterns as $id) {
            $user = new \User($id, 'User', 'email@example.com', '', ROLE_STUDENT, true);
            $this->assertTrue($user->isDummy(), "ID $id should be detected as dummy");
        }
    }

    /**
     * Test __toString() returns user ID
     */
    public function testToStringReturnsUserId()
    {
        $this->assertEquals('joedoe', (string)$this->user);
    }

    /**
     * Test getPhoto() returns provided photo when available
     */
    public function testGetPhotoReturnsProvidedPhoto()
    {
        $photo = 'https://example.com/my-photo.jpg';
        $user = new \User('user', 'User', 'email@example.com', $photo, ROLE_STUDENT, false);
        $this->assertEquals($photo, $user->getPhoto());
    }

    /**
     * Test getPhoto() returns default Fenix photo when empty
     */
    public function testGetPhotoReturnsDefaultPhotoWhenEmpty()
    {
        $user = new \User('joedoe', 'Joe', 'joe@example.com', '', ROLE_STUDENT, false);
        $photo = $user->getPhoto();
        
        $this->assertStringContainsString('fenix.tecnico.ulisboa.pt', $photo);
        $this->assertStringContainsString('joedoe', $photo);
        $this->assertStringStartsWith('https://', $photo);
    }

    /**
     * Test roleAtLeast() returns true when user role is high enough
     */
    public function testRoleAtLeastReturnsTrueWhenRoleHighEnough()
    {
        $this->assertTrue($this->user->roleAtLeast(ROLE_PROF));
    }

    /**
     * Test roleAtLeast() returns true when checking lower roles
     */
    public function testRoleAtLeastReturnsTrueForLowerRoles()
    {
        $this->assertTrue($this->user->roleAtLeast(ROLE_STUDENT));
    }

    /**
     * Test roleAtLeast() returns false for higher roles
     */
    public function testRoleAtLeastReturnsFalseForHigherRoles()
    {
        $this->assertFalse($this->user->roleAtLeast(ROLE_SUDO));
    }

    /**
     * Test getGroup() returns null when user has no groups
     */
    public function testGetGroupReturnsNullWhenNoGroups()
    {
        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);
        $this->assertNull($user->getGroup());
    }

    /**
     * Test getGroup() returns group for current year
     */
    public function testGetGroupReturnsCurrentYearGroup()
    {
        // Create shift and group with CURRENT year (not hardcoded)
        $currentYear = get_current_year();
        $shift = new \Shift('Monday 10:00', $currentYear);
        $group = new \ProjGroup(1, $currentYear, $shift);
        $group->project_name = 'Test Project';

        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);
        $user->groups->add($group);

        // Should return the group with current year
        $result = $user->getGroup();
        $this->assertNotNull($result);
        $this->assertEquals($currentYear, $result->year);
    }

    /**
     * Test getGroup() returns the first year-descending group when there is no current-year match
     */
    public function testGetGroupReturnsFirstOrderedGroupWhenNoCurrentYearMatch()
    {
        $shift = new \Shift('Monday 10:00', 2024);
        $oldGroup = new \ProjGroup(1, 2020, $shift);
        $oldGroup->project_name = 'Old Project';
        $newGroup = new \ProjGroup(2, 2023, $shift);
        $newGroup->project_name = 'New Project';

        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);
        $user->groups->add($newGroup);
        $user->groups->add($oldGroup);

        $result = $user->getGroup();
        $this->assertSame($newGroup, $result);
    }

    /**
     * Test getYear() returns null when user has no groups
     */
    public function testGetYearReturnsNullWhenNoGroups()
    {
        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);
        $this->assertNull($user->getYear());
    }

    /**
     * Test getYear() returns year from user's group
     */
    public function testGetYearReturnsYearFromGroup()
    {
        $shift = new \Shift('Monday 10:00', 2024);
        $group = new \ProjGroup(1, 2024, $shift);
        $group->project_name = 'Test Project';

        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);
        $user->groups->add($group);

        $this->assertEquals(2024, $user->getYear());
    }

    /**
     * Test getRole() returns role string
     */
    public function testGetRoleReturnsRoleString()
    {
        $roleString = $this->user->getRole();
        $this->assertIsString($roleString);
        $this->assertEquals('Professor', $roleString);
    }

    /**
     * Test getRole() for student role
     */
    public function testGetRoleForStudentRole()
    {
        $user = new \User('student', 'Student', 'student@example.com', '', ROLE_STUDENT, false);
        $roleString = $user->getRole();
        $this->assertEquals('Student', $roleString);
    }

    /**
     * Test getRole() for TA role
     */
    public function testGetRoleForTARole()
    {
        $user = new \User('ta', 'TA', 'ta@example.com', '', ROLE_TA, false);
        $roleString = $user->getRole();
        $this->assertEquals('TA', $roleString);
    }

    /**
     * Test getRepoUser() returns null when no repository user set
     */
    public function testGetRepoUserReturnsNullWhenNotSet()
    {
        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);
        $user->repository_user = '';
        $this->assertNull($user->getRepoUser());
    }

    /**
     * Test getRepoUser() returns RepositoryUser when set
     */
    public function testGetRepoUserReturnsRepositoryUserWhenSet()
    {
        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);
        $user->repository_user = 'github:username';
        
        $repoUser = $user->getRepoUser();
        $this->assertNotNull($repoUser);
        $this->assertInstanceOf(\RepositoryUser::class, $repoUser);
        $this->assertEquals($user, $repoUser->user);
    }

    /**
     * Test groups collection is ArrayCollection
     */
    public function testGroupsIsArrayCollection()
    {
        $this->assertInstanceOf(\Doctrine\Common\Collections\ArrayCollection::class, $this->user->groups);
    }

    /**
     * Test repository_user default value is empty string
     */
    public function testRepositoryUserDefaultValue()
    {
        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);
        $this->assertEquals('', $user->repository_user);
    }

    /**
     * Test multiple users can have different groups
     */
    public function testMultipleUsersCanHaveDifferentGroups()
    {
        $shift = new \Shift('Monday 10:00', 2024);
        $group1 = new \ProjGroup(1, 2024, $shift);
        $group1->project_name = 'Project 1';
        $group2 = new \ProjGroup(2, 2024, $shift);
        $group2->project_name = 'Project 2';

        $user1 = new \User('user1', 'User 1', 'user1@example.com', '', ROLE_STUDENT, false);
        $user2 = new \User('user2', 'User 2', 'user2@example.com', '', ROLE_STUDENT, false);

        $user1->groups->add($group1);
        $user2->groups->add($group2);

        $this->assertEquals($group1, $user1->getGroup());
        $this->assertEquals($group2, $user2->getGroup());
    }

    /**
     * Test set_repository_user() parses simple format
     */
    public function testSetRepositoryUserParsesSimpleFormat()
    {
        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);

        $user->set_repository_user('github:username');

        $this->assertEquals('github:username', $user->repository_user);
    }

    /**
     * Test set_repository_user() resets on ValidationException for invalid format
     */
    public function testSetRepositoryUserResetOnInvalidFormat()
    {
        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);
        $user->repository_user = 'old-value';
        
        try {
            // Invalid format (no colon separator)
            $user->set_repository_user('invalidformat');
            $this->fail('Should throw ValidationException for invalid format');
        } catch (\ValidationException $e) {
            // Should be reset to empty string when exception occurs
            $this->assertEquals('', $user->repository_user);
            $this->assertStringContainsString('provider:username', $e->getMessage());
        }
    }

    /**
     * Test set_repository_user() resets on invalid platform
     */
    public function testSetRepositoryUserResetOnInvalidPlatform()
    {
        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);
        $user->repository_user = 'old-value';
        
        try {
            // Invalid platform
            $user->set_repository_user('gitlab:username');
            $this->fail('Should throw ValidationException for unsupported platform');
        } catch (\ValidationException $e) {
            $this->assertEquals('', $user->repository_user);
            $this->assertStringContainsString('platform', $e->getMessage());
        }
    }

    /**
     * Test set_repository_user() handles URL parsing
     */
    public function testSetRepositoryUserHandlesUrlParsing()
    {
        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);

        $user->set_repository_user('https://github.com/validuser');

        $this->assertEquals('github:validuser', $user->repository_user);
    }

    /**
     * Test set_repository_user() resets value on exception
     */
    public function testSetRepositoryUserResetsOnException()
    {
        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);
        $originalValue = 'github:original';
        $user->repository_user = $originalValue;
        
        try {
            // Try invalid format that will definitely throw
            $user->set_repository_user('bad_format_no_colon');
            $this->fail('Should throw ValidationException for invalid format');
        } catch (\ValidationException $e) {
            // Verify the value was reset to empty, not left at original
            $this->assertNotEquals($originalValue, $user->repository_user);
            $this->assertEquals('', $user->repository_user);
            $this->assertStringContainsString('provider:username', $e->getMessage());
        }
    }

    /**
     * Test set_repository_user() with valid format and no group
     */
    public function testSetRepositoryUserValidFormatNoGroup()
    {
        $user = new \User('user', 'User', 'email@example.com', '', ROLE_STUDENT, false);

        $user->set_repository_user('github:testuser');

        $this->assertEquals('github:testuser', $user->repository_user);
    }

    /**
     * Test repository_user property defaults to empty string
     */
    public function testRepositoryUserDefaultsToEmptyString()
    {
        $this->assertEquals('', $this->user->repository_user);
    }

    /**
     * Test repository_etag property defaults to empty string
     */
    public function testRepositoryEtagDefaultsToEmptyString()
    {
        $this->assertEquals('', $this->user->repository_etag);
    }

    /**
     * Test repository_last_processed_id defaults to zero
     */
    public function testRepositoryLastProcessedIdDefaultsToZero()
    {
        $this->assertEquals('0', $this->user->repository_last_processed_id);
    }

    /**
     * Test groups collection is initialized
     */
    public function testGroupsCollectionIsInitialized()
    {
        $this->assertNotNull($this->user->groups);
        $this->assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $this->user->groups);
        $this->assertTrue($this->user->groups->isEmpty());
    }

    /**
     * Test user email is stored correctly
     */
    public function testEmailIsStoredCorrectly()
    {
        $this->assertEquals('joe@example.com', $this->user->email);
        
        $user2 = new \User('user', 'User', 'another@test.org', '', ROLE_STUDENT, false);
        $this->assertEquals('another@test.org', $user2->email);
    }

    /**
     * Test user role is stored correctly
     */
    public function testRoleIsStoredCorrectly()
    {
        $this->assertEquals(ROLE_PROF, $this->user->role);
        
        $sudoUser = new \User('admin', 'Admin', 'admin@example.com', '', ROLE_SUDO, false);
        $this->assertEquals(ROLE_SUDO, $sudoUser->role);
    }

    /**
     * Test that user constructor accepts dummy parameter
     */
    public function testConstructorAcceptsDummyParameter()
    {
        $user1 = new \User('user1', 'User', 'user@example.com', '', ROLE_STUDENT, false);
        $this->assertFalse($user1->isDummy());

        $user2 = new \User('ist0000001', 'Dummy', 'dummy@example.com', '', ROLE_STUDENT, true);
        $this->assertTrue($user2->isDummy());
    }

    /**
     * Test that different users have different instances
     */
    public function testDifferentUsersAreDifferentInstances()
    {
        $user1 = new \User('user1', 'User One', 'user1@example.com', '', ROLE_STUDENT, false);
        $user2 = new \User('user2', 'User Two', 'user2@example.com', '', ROLE_STUDENT, false);

        $this->assertNotSame($user1, $user2);
        $this->assertNotEquals($user1->id, $user2->id);
    }

    /**
     * Test user can be updated after creation
     */
    public function testUserPropertiesCanBeUpdated()
    {
        $originalName = $this->user->name;
        $this->user->name = 'Jane Doe';
        
        $this->assertNotEquals($originalName, $this->user->name);
        $this->assertEquals('Jane Doe', $this->user->name);
    }

    /**
     * Test email validation format
     */
    public function testEmailFormatExamples()
    {
        $validEmails = [
            'user@example.com',
            'john.doe@company.co.uk',
            'test123@domain.org',
            'first.last+tag@subdomain.example.com',
        ];

        foreach ($validEmails as $email) {
            $user = new \User('user', 'User', $email, '', ROLE_STUDENT, false);
            $this->assertEquals($email, $user->email);
        }
    }
}

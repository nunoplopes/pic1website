<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;


use ValidationException;

/**
 * Test suite for Grade entity
 * 
 * Tests grade creation, field validation, and late days tracking
 */
class GradeTest extends UnitTestCase
{
    private \User $testUser;
    private \Milestone $testMilestone;
    private \Grade $grade;

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

        // Create test milestone with required constructor parameters
        $this->testMilestone = new \Milestone(2024, 'Sprint 1');
        $this->testMilestone->description = 'First sprint';
        $this->testMilestone->individual = false;
        $this->testMilestone->field1 = 'Functionality';
        $this->testMilestone->range1 = 100;
        $this->testMilestone->field2 = 'Code Quality';
        $this->testMilestone->range2 = 100;
        $this->testMilestone->field3 = '';
        $this->testMilestone->field4 = '';

        // Create test grade
        $this->grade = new \Grade();
        $this->grade->user = $this->testUser;
        $this->grade->milestone = $this->testMilestone;
        $this->grade->field1 = 85;
        $this->grade->field2 = 90;
        $this->grade->field3 = null;
        $this->grade->field4 = null;
        $this->grade->late_days = 0;
    }

    /**
     * Test that grade can be created with user and milestone
     */
    public function testGradeCanBeCreated()
    {
        $this->assertInstanceOf(\Grade::class, $this->grade);
        $this->assertEquals($this->testUser, $this->grade->user);
        $this->assertEquals($this->testMilestone, $this->grade->milestone);
    }

    /**
     * Test that grade fields can be set
     */
    public function testGradeFieldsCanBeSet()
    {
        $this->assertEquals(85, $this->grade->field1);
        $this->assertEquals(90, $this->grade->field2);
    }

    /**
     * Test that late_days defaults to zero
     */
    public function testLateDaysDefaultsToZero()
    {
        $grade = new \Grade();
        $grade->user = $this->testUser;
        $grade->milestone = $this->testMilestone;
        
        $this->assertEquals(0, $grade->late_days);
    }

    /**
     * Test that grade fields can be null
     */
    public function testGradeFieldsCanBeNull()
    {
        $this->grade->field3 = null;
        $this->grade->field4 = null;
        
        $this->assertNull($this->grade->field3);
        $this->assertNull($this->grade->field4);
    }

    /**
     * Test that late_days can be set
     */
    public function testLateDaysCanBeSet()
    {
        $this->grade->late_days = 5;
        $this->assertEquals(5, $this->grade->late_days);
    }

    /**
     * Test that grade fields accept various valid values
     */
    public function testGradeFieldsAcceptValidValues()
    {
        $this->grade->field1 = 0;
        $this->assertEquals(0, $this->grade->field1);
        
        $this->grade->field1 = 50;
        $this->assertEquals(50, $this->grade->field1);
        
        $this->grade->field1 = 100;
        $this->assertEquals(100, $this->grade->field1);
    }

    /**
     * Test validation passes for valid field values within range
     */
    public function testValidateFieldsPassesForValidValues()
    {
        $this->grade->field1 = 50;
        $this->grade->field2 = 75;
        $this->grade->late_days = 2;

        $this->grade->validateFields();

        $this->assertEquals(50, $this->grade->field1);
        $this->assertEquals(75, $this->grade->field2);
        $this->assertEquals(2, $this->grade->late_days);
    }

    /**
     * Test validation passes when fields are null for empty milestone fields
     */
    public function testValidateFieldsPassesForNullFields()
    {
        $this->grade->field3 = null;
        $this->grade->field4 = null;

        $this->grade->validateFields();

        $this->assertNull($this->grade->field3);
        $this->assertNull($this->grade->field4);
    }

    /**
     * Test validation fails for field value exceeding range
     */
    public function testValidateFieldsFailsForExceededRange()
    {
        $this->grade->field1 = 150; // Exceeds range of 100

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("exceeds allowed range");

        $this->grade->validateFields();
    }

    /**
     * Test validation fails for negative field values
     */
    public function testValidateFieldsFailsForNegativeValues()
    {
        $this->grade->field1 = -5;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("exceeds allowed range");

        $this->grade->validateFields();
    }

    /**
     * Test validation fails for negative late_days
     */
    public function testValidateFieldsFailsForNegativeLateDays()
    {
        $this->grade->late_days = -1;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Late days cannot be negative");

        $this->grade->validateFields();
    }

    /**
     * Test all field ranges are validated
     */
    public function testValidateFieldsChecksAllRanges()
    {
        $this->testMilestone->field3 = 'Design';
        $this->testMilestone->range3 = 50;
        $this->grade->field3 = 75; // Exceeds range of 50

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 3 exceeds allowed range");

        $this->grade->validateFields();
    }

    /**
     * Test zero late days is valid
     */
    public function testValidateFieldsAllowsZeroLateDays()
    {
        $this->grade->late_days = 0;

        $this->grade->validateFields();

        $this->assertEquals(0, $this->grade->late_days);
    }

    /**
     * Test boundary values for field ranges
     */
    public function testValidateFieldsBoundaryValues()
    {
        // Test minimum boundary
        $this->grade->field1 = 0;

        $this->grade->validateFields();
        $this->assertEquals(0, $this->grade->field1);
        
        // Test maximum boundary
        $this->grade->field1 = 100;

        $this->grade->validateFields();
        $this->assertEquals(100, $this->grade->field1);
    }

    /**
     * Test that grade can be created for different users
     */
    public function testGradeCanBeCreatedForDifferentUsers()
    {
        $user1 = new \User('user1', 'User 1', 'user1@example.com', '', ROLE_STUDENT, false);
        $user2 = new \User('user2', 'User 2', 'user2@example.com', '', ROLE_STUDENT, false);

        $grade1 = new \Grade();
        $grade1->user = $user1;
        $grade1->milestone = $this->testMilestone;
        $grade1->field1 = 80;

        $grade2 = new \Grade();
        $grade2->user = $user2;
        $grade2->milestone = $this->testMilestone;
        $grade2->field1 = 90;

        $this->assertEquals('user1', $grade1->user->id);
        $this->assertEquals('user2', $grade2->user->id);
        $this->assertEquals(80, $grade1->field1);
        $this->assertEquals(90, $grade2->field1);
    }

    /**
     * Test that grade references are preserved
     */
    public function testGradeReferencesArePreserved()
    {
        $this->assertSame($this->testUser, $this->grade->user);
        $this->assertSame($this->testMilestone, $this->grade->milestone);
    }

    /**
     * Test that late_days can track multiple days
     */
    public function testLateDaysCanTrackMultipleDays()
    {
        for ($days = 0; $days <= 10; $days++) {
            $grade = new \Grade();
            $grade->user = $this->testUser;
            $grade->milestone = $this->testMilestone;
            $grade->late_days = $days;
            
            $this->assertEquals($days, $grade->late_days);
        }
    }

    /**
     * Test that grade instances are independent
     */
    public function testGradeInstancesAreIndependent()
    {
        $grade1 = new \Grade();
        $grade1->user = $this->testUser;
        $grade1->milestone = $this->testMilestone;
        $grade1->field1 = 80;

        $grade2 = new \Grade();
        $grade2->user = $this->testUser;
        $grade2->milestone = $this->testMilestone;
        $grade2->field1 = 90;

        $this->assertEquals(80, $grade1->field1);
        $this->assertEquals(90, $grade2->field1);
    }

    /**
     * Test grade field types
     */
    public function testGradeFieldTypesArePreserved()
    {
        $this->assertIsInt($this->grade->field1);
        $this->assertIsInt($this->grade->field2);
        $this->assertIsInt($this->grade->late_days);
    }

    /**
     * Test that all four field properties exist
     */
    public function testAllFourFieldPropertiesExist()
    {
        $reflection = new \ReflectionClass(\Grade::class);
        
        $this->assertTrue($reflection->hasProperty('field1'));
        $this->assertTrue($reflection->hasProperty('field2'));
        $this->assertTrue($reflection->hasProperty('field3'));
        $this->assertTrue($reflection->hasProperty('field4'));
    }

    /**
     * Test grade with empty fields
     */
    public function testGradeWithEmptyFields()
    {
        $grade = new \Grade();
        $grade->user = $this->testUser;
        
        // Create milestone with empty fields
        $milestone = new \Milestone(2024, 'Test');
        $milestone->field1 = '';  // Empty means not graded
        $milestone->field2 = '';
        $milestone->field3 = '';
        $milestone->field4 = '';
        
        $grade->milestone = $milestone;
        
        // Initialize fields to null
        $grade->field1 = null;
        $grade->field2 = null;
        $grade->field3 = null;
        $grade->field4 = null;
        
        // Fields should be null when milestone fields are empty
        $this->assertNull($grade->field1);
        $this->assertNull($grade->field2);
        $this->assertNull($grade->field3);
        $this->assertNull($grade->field4);
    }
}

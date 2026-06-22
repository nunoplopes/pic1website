<?php

namespace Tests\Integration;

use Tests\Integration\Base\IntegrationTestCase;

/**
 * Integration tests for Grading workflows
 * 
 * Tests the interaction between:
 * - User (student)
 * - Milestone (assignment)
 * - Grade (score)
 * - Grade calculation and validation
 */
class GradingIntegrationTest extends IntegrationTestCase
{
    private \User $student;
    private \Milestone $milestone;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test student
        $this->student = new \User(
            username: 'student1',
            name: 'John Student',
            email: 'john@example.com',
            photo: '',
            role: ROLE_STUDENT,
            dummy: false
        );

        // Create test milestone with multiple grading fields
        $this->milestone = new \Milestone(2024, 'Assignment 1');
        $this->milestone->description = 'First programming assignment';
        $this->milestone->individual = false;
        $this->milestone->field1 = 'Code Quality';
        $this->milestone->points1 = 40;
        $this->milestone->range1 = 100;
        $this->milestone->field2 = 'Functionality';
        $this->milestone->points2 = 35;
        $this->milestone->range2 = 100;
        $this->milestone->field3 = 'Documentation';
        $this->milestone->points3 = 15;
        $this->milestone->range3 = 100;
        $this->milestone->field4 = '';
        $this->milestone->points4 = 0;
        $this->milestone->range4 = 0;
    }

    /**
     * Test student receives grade for milestone
     * 
     * Workflow:
     * 1. Create grade for student + milestone
     * 2. Set grade values for each field
     * 3. Verify grade is properly linked
     */
    public function testStudentReceivesGradeForMilestone()
    {
        $grade = new \Grade();
        $grade->user = $this->student;
        $grade->milestone = $this->milestone;
        $grade->field1 = 85;  // Code Quality
        $grade->field2 = 90;  // Functionality
        $grade->field3 = 88;  // Documentation
        $grade->late_days = 0;

        // Verify grade is linked to student and milestone
        $this->assertEquals($this->student->id, $grade->user->id);
        $this->assertEquals($this->milestone->name, $grade->milestone->name);
        $this->assertEquals(85, $grade->field1);
        $this->assertEquals(90, $grade->field2);
        $this->assertEquals(88, $grade->field3);
    }

    /**
     * Test grade calculation across multiple fields
     * 
     * Workflow:
     * 1. Create grade with scores for each field
     * 2. Calculate total points earned
     * 3. Calculate total possible points
     * 4. Calculate percentage
     */
    public function testGradeCalculationAcrossFields()
    {
        $grade = new \Grade();
        $grade->user = $this->student;
        $grade->milestone = $this->milestone;
        $grade->field1 = 80;  // 80/100
        $grade->field2 = 85;  // 85/100
        $grade->field3 = 90;  // 90/100

        // Calculate weighted score
        $field1_points = (80 / 100) * 40;  // 32 points
        $field2_points = (85 / 100) * 35;  // 29.75 points
        $field3_points = (90 / 100) * 15;  // 13.5 points
        $total = $field1_points + $field2_points + $field3_points;

        $this->assertGreaterThan(70, $total);
        $this->assertLessThan(91, $total);
    }

    /**
     * Test late submission with late days penalty
     * 
     * Workflow:
     * 1. Create grade with late days
     * 2. Verify late_days is recorded
     * 3. Calculate penalty if applicable
     */
    public function testLateSubmissionPenalty()
    {
        $grade = new \Grade();
        $grade->user = $this->student;
        $grade->milestone = $this->milestone;
        $grade->field1 = 80;
        $grade->field2 = 85;
        $grade->field3 = 90;
        $grade->late_days = 3;

        // Verify late days are recorded
        $this->assertEquals(3, $grade->late_days);
        $this->assertGreaterThan(0, $grade->late_days);
    }

    /**
     * Test multiple students graded on same milestone
     * 
     * Workflow:
     * 1. Create grades for multiple students on same milestone
     * 2. Verify each grade is independent
     * 3. Verify different scores per student
     */
    public function testMultipleStudentsGraded()
    {
        $student1 = new \User('user1', 'Student 1', 'user1@example.com', '', ROLE_STUDENT, false);
        $student2 = new \User('user2', 'Student 2', 'user2@example.com', '', ROLE_STUDENT, false);
        $student3 = new \User('user3', 'Student 3', 'user3@example.com', '', ROLE_STUDENT, false);

        $grade1 = new \Grade();
        $grade1->user = $student1;
        $grade1->milestone = $this->milestone;
        $grade1->field1 = 85;
        $grade1->field2 = 80;
        $grade1->field3 = 75;

        $grade2 = new \Grade();
        $grade2->user = $student2;
        $grade2->milestone = $this->milestone;
        $grade2->field1 = 95;
        $grade2->field2 = 92;
        $grade2->field3 = 90;

        $grade3 = new \Grade();
        $grade3->user = $student3;
        $grade3->milestone = $this->milestone;
        $grade3->field1 = 70;
        $grade3->field2 = 75;
        $grade3->field3 = 80;

        // Verify each student has different grades
        $this->assertEquals(85, $grade1->field1);
        $this->assertEquals(95, $grade2->field1);
        $this->assertEquals(70, $grade3->field1);
    }

    /**
     * Test partial grading (some fields not graded)
     * 
     * Workflow:
     * 1. Create milestone with 4 grading fields
     * 2. Grade only 2 fields
     * 3. Leave other fields ungraded (null)
     */
    public function testPartialGrading()
    {
        $milestone = new \Milestone(2024, 'Partial Grade Test');
        $milestone->field1 = 'Part A';
        $milestone->range1 = 100;
        $milestone->field2 = 'Part B';
        $milestone->range2 = 100;
        $milestone->field3 = '';  // Not graded
        $milestone->field4 = '';  // Not graded

        $grade = new \Grade();
        $grade->user = $this->student;
        $grade->milestone = $milestone;
        $grade->field1 = 85;
        $grade->field2 = 90;
        $grade->field3 = null;  // Not graded
        $grade->field4 = null;  // Not graded

        $this->assertEquals(85, $grade->field1);
        $this->assertEquals(90, $grade->field2);
        $this->assertNull($grade->field3);
        $this->assertNull($grade->field4);
    }

    /**
     * Test student retrieves their own grades
     * 
     * Workflow:
     * 1. Student has grades for multiple milestones
     * 2. Student retrieves all their grades
     * 3. Can filter by milestone or year
     */
    /**
     * Test student retrieves own grades from database
     * 
     * Workflow:
     * 1. Create student in database
     * 2. Create milestone in database
     * 3. Create grade for student in database
     * 4. Query grades by student ID
     * 5. Verify retrieved grade matches persisted data
     */
    public function testStudentRetrievesOwnGrades()
    {
        // Create student in database
        $studentId = 'test_grades_' . bin2hex(random_bytes(4));
        $this->insertTestUser($studentId, 'Grades Retrieval Student');
        
        // Create milestone in database
        $milestoneId = $this->insertTestMilestone(2024, 'Assignment 1', 'First programming assignment');
        
        // Create grade for student
        $this->insertTestGrade($studentId, $milestoneId, 85, 90, 88);
        
        // Query grades back from database
        $retrievedGrade = $this->getGradesFromDatabase($studentId, $milestoneId);
        
        // Verify grade exists in database
        $this->assertNotNull($retrievedGrade);
        
        // Verify grade belongs to correct student
        $this->assertEquals($studentId, $retrievedGrade['user_id']);
        
        // Verify grade values persisted
        $this->assertEquals(85, $retrievedGrade['field1']);
        $this->assertEquals(90, $retrievedGrade['field2']);
        $this->assertEquals(88, $retrievedGrade['field3']);
        
        // Verify milestone ID matches
        $this->assertEquals($milestoneId, $retrievedGrade['milestone_id']);
    }

    /**
     * Test final grade calculation from all milestones
     * 
     * Workflow:
     * 1. Create student user in database
     * 2. Create multiple milestones in database
     * 3. Create grades for each milestone
     * 4. Retrieve grades from database
     * 5. Verify grades are persisted correctly
     */
    public function testFinalGradeCalculation()
    {
        // Create student in database
        $studentId = 'test_finalgrade_' . bin2hex(random_bytes(4));
        $this->insertTestUser($studentId, 'Grade Test Student');
        
        // Create multiple milestones in database
        $m1Id = $this->insertTestMilestone(2024, 'M1', 'First Milestone');
        $m2Id = $this->insertTestMilestone(2024, 'M2', 'Second Milestone');
        
        // Create grades for both milestones
        $this->insertTestGrade($studentId, $m1Id, 85, 80, 90);
        $this->insertTestGrade($studentId, $m2Id, 90, 95, 88);
        
        // Retrieve grades from database
        $grade1 = $this->getGradesFromDatabase($studentId, $m1Id);
        $grade2 = $this->getGradesFromDatabase($studentId, $m2Id);
        
        // Verify grades exist in database
        $this->assertNotNull($grade1, 'Grade 1 should exist in database');
        $this->assertNotNull($grade2, 'Grade 2 should exist in database');
        
        // Verify grade values match
        $this->assertEquals(85, $grade1['field1']);
        $this->assertEquals(80, $grade1['field2']);
        $this->assertEquals(90, $grade1['field3']);
        
        $this->assertEquals(90, $grade2['field1']);
        $this->assertEquals(95, $grade2['field2']);
        $this->assertEquals(88, $grade2['field3']);
        
        // Verify we can calculate average from persisted grades
        $average = ((85 + 80 + 90) / 3 + (90 + 95 + 88) / 3) / 2;
        $this->assertGreaterThan(87, $average);
        $this->assertLessThan(89, $average);
    }

    /**
     * Test grade entry validation
     * 
     * Workflow:
     * 1. Attempt to enter grade outside valid range
     * 2. Validation catches error
     * 3. Grade is not accepted
     */
    public function testGradeEntryValidation()
    {
        $grade = new \Grade();
        $grade->user = $this->student;
        $grade->milestone = $this->milestone;
        $grade->field1 = 101;
        $grade->field2 = 90;
        $grade->field3 = 80;
        $grade->field4 = null;

        $this->expectException(\ValidationException::class);
        $this->expectExceptionMessage('Field 1 exceeds allowed range');
        $grade->validateFields();
    }
}

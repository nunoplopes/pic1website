<?php

namespace Tests\Integration;

use Tests\Integration\Base\IntegrationTestCase;

/**
 * Integration tests for User and Project Group workflows
 * 
 * Tests the interaction between:
 * - User entities
 * - Project Group enrollment
 * - User role permissions
 * - User project assignments
 */
class UserProjectGroupIntegrationTest extends IntegrationTestCase
{
    private \User $student;
    private \User $professor;
    private \Shift $testShift;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->student = new \User(
            username: 'student1',
            name: 'Alice Student',
            email: 'alice@example.com',
            photo: '',
            role: ROLE_STUDENT,
            dummy: false
        );

        $this->professor = new \User(
            username: 'prof1',
            name: 'Prof. Teacher',
            email: 'prof@example.com',
            photo: '',
            role: ROLE_PROF,
            dummy: false
        );

        // Create test shift
        $this->testShift = new \Shift('Monday 10:00', 2024);
    }

    /**
     * Test user enrollment in project group workflow
     * 
     * Workflow:
     * 1. Create new project group
     * 2. Add student to group
     * 3. Verify student is in group
     * 4. Verify group has student
     */
    public function testUserEnrollmentInProjectGroup()
    {
        // Create a project group
        $group = new \ProjGroup(1, 2024, $this->testShift);
        $group->project_name = 'Awesome Project';
        $group->project_description = 'An awesome student project';
        $group->repository = 'https://github.com/example/project';

        // Add student to group
        $group->students->add($this->student);

        // Verify student is in group
        $this->assertTrue($group->students->contains($this->student));
        $this->assertCount(1, $group->students);
    }

    /**
     * Test multiple students in same project group
     * 
     * Workflow:
     * 1. Create project group with multiple students
     * 2. Verify each student is added
     * 3. Verify group has correct student count
     */
    public function testMultipleStudentsInProjectGroup()
    {
        $student1 = new \User('user1', 'Student 1', 'user1@example.com', '', ROLE_STUDENT, false);
        $student2 = new \User('user2', 'Student 2', 'user2@example.com', '', ROLE_STUDENT, false);
        $student3 = new \User('user3', 'Student 3', 'user3@example.com', '', ROLE_STUDENT, false);

        $group = new \ProjGroup(2, 2024, $this->testShift);

        // Add multiple students
        $group->students->add($student1);
        $group->students->add($student2);
        $group->students->add($student3);

        // Verify all students are in group
        $this->assertCount(3, $group->students);
        $this->assertTrue($group->students->contains($student1));
        $this->assertTrue($group->students->contains($student2));
        $this->assertTrue($group->students->contains($student3));
    }

    /**
     * Test student's current year group retrieval
     * 
     * Workflow:
     * 1. Create multiple groups for same student in different years
     * 2. Call getGroup() to get current year group
     * 3. Verify correct group is returned
     */
    public function testGetCurrentYearGroup()
    {
        $currentYear = get_current_year();
        $oldGroup = new \ProjGroup(1, $currentYear - 1, $this->testShift);
        $currentGroup = new \ProjGroup(2, $currentYear, $this->testShift);

        $oldGroup->addStudent($this->student);
        $currentGroup->addStudent($this->student);

        $this->assertSame($currentGroup, $this->student->getGroup());
    }

    /**
     * Test professor can view group details
     * 
     * Workflow:
     * 1. Professor creates project group
     * 2. Professor assigns students
     * 3. Professor can view all group members
     * 4. Professor can view project repository
     */
    public function testProfessorManagesProjectGroup()
    {
        $group = new \ProjGroup(1, 2024, $this->testShift);
        $group->project_name = 'Student Project';
        $group->repository = 'https://github.com/students/project';
        $group->students = new \Doctrine\Common\Collections\ArrayCollection();
        $group->students->add($this->student);

        // Professor can view project details
        $this->assertEquals('Student Project', $group->project_name);
        $this->assertEquals('https://github.com/students/project', $group->repository);
        $this->assertCount(1, $group->students);
    }

    /**
     * Test project group with repository configuration
     * 
     * Workflow:
     * 1. Create project group with repository URL
     * 2. Configure CLA/DCO requirements
     * 3. Verify repository can be referenced
     */
    public function testProjectGroupRepositoryConfiguration()
    {
        $group = new \ProjGroup(1, 2024, $this->testShift);
        $group->repository = 'https://github.com/example/project';
        $group->cla = true;
        $group->dco = false;

        // Verify repository is set
        $this->assertNotEmpty($group->repository);
        $this->assertTrue($group->cla);
        $this->assertFalse($group->dco);
    }

    /**
     * Test student group data persistence
     * 
     * Workflow:
     * 1. Create and populate project group
     * 2. Retrieve group information
     * 3. Verify data is consistent
     */
    /**
     * Test group data persistence in database
     * 
     * Workflow:
     * 1. Create shift in database
     * 2. Create project group in database with properties
     * 3. Add student user to group
     * 4. Query group back from database
     * 5. Verify all properties persisted correctly
     * 6. Verify student membership is persisted
     */
    public function testGroupDataPersistence()
    {
        $year = 2024;
        
        // Create shift
        $shiftId = $this->insertTestShift($year, 'Test Shift');
        
        // Create student
        $studentId = 'test_group_' . bin2hex(random_bytes(4));
        $this->insertTestUser($studentId, 'Group Test Student');
        
        // Create project group
        $groupId = $this->insertTestGroup(5, $year, $shiftId, 'Test Project');
        
        // Add student to group
        $this->insertGroupMembership($groupId, $studentId);
        
        // Query group back from database
        $retrievedGroup = $this->getGroupFromDatabase($groupId);
        
        // Verify group exists and data persisted
        $this->assertNotNull($retrievedGroup);
        $this->assertEquals(5, $retrievedGroup['group_number']);
        $this->assertEquals($year, $retrievedGroup['year']);
        $this->assertEquals('Test Project', $retrievedGroup['project_name']);
        $this->assertEquals($shiftId, $retrievedGroup['shift_id']);
        
        // Verify student membership persisted
        $members = $this->getGroupMembersFromDatabase($groupId);
        $this->assertCount(1, $members);
        $this->assertContains($studentId, $members);
    }

    /**
     * Test user retrieves their current group
     * 
     * Workflow:
     * 1. Student is member of group
     * 2. Student can call getGroup()
     * 3. Student gets correct current year group
     * 4. Student can access group project info
     */
    public function testStudentRetrievesCurrentGroup()
    {
        $currentYear = get_current_year();
        $group = new \ProjGroup(7, $currentYear, $this->testShift);
        $group->project_name = 'Current Project';
        $group->addStudent($this->student);

        $currentGroup = $this->student->getGroup();

        $this->assertSame($group, $currentGroup);
        $this->assertEquals('Current Project', $currentGroup->project_name);
    }
}

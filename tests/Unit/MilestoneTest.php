<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;



/**
 * Test suite for Milestone entity
 * 
 * Tests milestone creation, properties, and structure
 */
class MilestoneTest extends UnitTestCase
{
    private \Milestone $milestone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->milestone = new \Milestone(2024, 'Sprint 1');
        $this->milestone->description = 'First sprint of the project';
        $this->milestone->page = '/sprints/1';
        $this->milestone->individual = false;
        $this->milestone->field1 = 'Functionality';
        $this->milestone->points1 = 40;
        $this->milestone->range1 = 100;
        $this->milestone->field2 = 'Code Quality';
        $this->milestone->points2 = 30;
        $this->milestone->range2 = 100;
        $this->milestone->field3 = 'Documentation';
        $this->milestone->points3 = 20;
        $this->milestone->range3 = 100;
        $this->milestone->field4 = '';
        $this->milestone->points4 = 0;
        $this->milestone->range4 = 0;
    }

    /**
     * Test that milestone can be created
     */
    public function testMilestoneCanBeCreated()
    {
        $this->assertInstanceOf(\Milestone::class, $this->milestone);
    }

    /**
     * Test that milestone properties can be set and retrieved
     */
    public function testMilestonePropertiesCanBeSet()
    {
        $this->assertEquals(2024, $this->milestone->year);
        $this->assertEquals('Sprint 1', $this->milestone->name);
        $this->assertEquals('First sprint of the project', $this->milestone->description);
        $this->assertEquals('/sprints/1', $this->milestone->page);
    }

    /**
     * Test that milestone individual flag works
     */
    public function testMilestoneCanBeIndividualOrGroup()
    {
        $this->assertFalse($this->milestone->individual);
        
        $this->milestone->individual = true;
        $this->assertTrue($this->milestone->individual);
    }

    /**
     * Test that milestone fields can be configured
     */
    public function testMilestoneFieldsCanBeConfigured()
    {
        $this->assertEquals('Functionality', $this->milestone->field1);
        $this->assertEquals('Code Quality', $this->milestone->field2);
        $this->assertEquals('Documentation', $this->milestone->field3);
        $this->assertEquals('', $this->milestone->field4);
    }

    /**
     * Test that milestone points can be configured
     */
    public function testMilestonePointsCanBeConfigured()
    {
        $this->assertEquals(40, $this->milestone->points1);
        $this->assertEquals(30, $this->milestone->points2);
        $this->assertEquals(20, $this->milestone->points3);
        $this->assertEquals(0, $this->milestone->points4);
    }

    /**
     * Test that milestone ranges can be configured
     */
    public function testMilestoneRangesCanBeConfigured()
    {
        $this->assertEquals(100, $this->milestone->range1);
        $this->assertEquals(100, $this->milestone->range2);
        $this->assertEquals(100, $this->milestone->range3);
        $this->assertEquals(0, $this->milestone->range4);
    }

    /**
     * Test default values for description
     */
    public function testDefaultDescription()
    {
        $milestone = new \Milestone(2024, 'Test');
        $this->assertEquals('', $milestone->description);
    }

    /**
     * Test default values for page
     */
    public function testDefaultPage()
    {
        $milestone = new \Milestone(2024, 'Test');
        $this->assertEquals('', $milestone->page);
    }

    /**
     * Test default values for individual
     */
    public function testDefaultIndividual()
    {
        $milestone = new \Milestone(2024, 'Test');
        $this->assertFalse($milestone->individual);
    }

    /**
     * Test default values for fields
     */
    public function testDefaultFields()
    {
        $milestone = new \Milestone(2024, 'Test');
        $this->assertEquals('', $milestone->field1);
        $this->assertEquals('', $milestone->field2);
        $this->assertEquals('', $milestone->field3);
        $this->assertEquals('', $milestone->field4);
    }

    /**
     * Test default values for points
     */
    public function testDefaultPoints()
    {
        $milestone = new \Milestone(2024, 'Test');
        $this->assertEquals(0, $milestone->points1);
        $this->assertEquals(0, $milestone->points2);
        $this->assertEquals(0, $milestone->points3);
        $this->assertEquals(0, $milestone->points4);
    }

    /**
     * Test default values for ranges
     */
    public function testDefaultRanges()
    {
        $milestone = new \Milestone(2024, 'Test');
        $this->assertEquals(0, $milestone->range1);
        $this->assertEquals(0, $milestone->range2);
        $this->assertEquals(0, $milestone->range3);
        $this->assertEquals(0, $milestone->range4);
    }

    /**
     * Test that milestone can have different configurations
     */
    public function testMilestoneWithDifferentConfigurations()
    {
        // 2-field milestone
        $m1 = new \Milestone(2024, 'M1');
        $m1->field1 = 'Part A';
        $m1->field2 = 'Part B';

        // 3-field milestone
        $m2 = new \Milestone(2024, 'M2');
        $m2->field1 = 'Part A';
        $m2->field2 = 'Part B';
        $m2->field3 = 'Part C';

        $this->assertEquals('', $m1->field3);
        $this->assertEquals('', $m1->field4);
        
        $this->assertEquals('Part C', $m2->field3);
        $this->assertEquals('', $m2->field4);
    }

    /**
     * Test milestone year property
     */
    public function testMilestoneYear()
    {
        $this->assertEquals(2024, $this->milestone->year);
        
        $milestone2024 = new \Milestone(2024, 'Test');
        $this->assertEquals(2024, $milestone2024->year);

        $milestone2025 = new \Milestone(2025, 'Test');
        $this->assertEquals(2025, $milestone2025->year);
    }

    /**
     * Test milestone name property
     */
    public function testMilestoneName()
    {
        $this->assertEquals('Sprint 1', $this->milestone->name);
        
        $this->milestone->name = 'Sprint 2';
        $this->assertEquals('Sprint 2', $this->milestone->name);
    }

    /**
     * Test that different milestones can exist
     */
    public function testDifferentMilestonesAreDifferent()
    {
        $m1 = new \Milestone(2024, 'Milestone 1');
        $m2 = new \Milestone(2024, 'Milestone 2');

        $this->assertNotSame($m1, $m2);
        $this->assertNotEquals($m1->name, $m2->name);
    }

    /**
     * Test milestone structure has all required properties
     */
    public function testMilestoneHasAllProperties()
    {
        $reflection = new \ReflectionClass(\Milestone::class);
        
        $requiredProperties = [
            'year', 'name', 'description', 'page',
            'individual',
            'field1', 'points1', 'range1',
            'field2', 'points2', 'range2',
            'field3', 'points3', 'range3',
            'field4', 'points4', 'range4'
        ];

        foreach ($requiredProperties as $property) {
            $this->assertTrue(
                $reflection->hasProperty($property),
                "Milestone should have property: $property"
            );
        }
    }
}

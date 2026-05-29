<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;


use Doctrine\Common\Collections\ArrayCollection;

/**
 * Test suite for Shift entity
 * 
 * Tests shift properties, group management, and string representation
 */
class ShiftTest extends UnitTestCase
{
    private \Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shift = new \Shift('T01', 2024);
    }

    /**
     * Test shift can be instantiated with name and year
     */
    public function testShiftCanBeCreated()
    {
        $this->assertInstanceOf(\Shift::class, $this->shift);
        $this->assertEquals('T01', $this->shift->name);
        $this->assertEquals(2024, $this->shift->year);
    }

    /**
     * Test shift has empty groups collection at creation
     */
    public function testShiftHasEmptyGroupsCollectionAtCreation()
    {
        $this->assertInstanceOf(ArrayCollection::class, $this->shift->groups);
        $this->assertEquals(0, $this->shift->groups->count());
    }

    /**
     * Test shift professor can be set and retrieved
     */
    public function testShiftProfessorCanBeSetAndRetrieved()
    {
        $professor = new \User('prof1', 'Professor Name', 'prof@example.com', '', ROLE_PROF, false);
        $this->shift->prof = $professor;
        
        $this->assertEquals($professor, $this->shift->prof);
        $this->assertEquals('prof1', $this->shift->prof->id);
    }

    /**
     * Test shift professor can be null
     */
    public function testShiftProfessorCanBeNull()
    {
        $this->shift->prof = null;
        
        $this->assertNull($this->shift->prof);
    }

    /**
     * Test shift name can be retrieved
     */
    public function testShiftNameCanBeRetrieved()
    {
        $this->assertEquals('T01', $this->shift->name);
        
        $newShift = new \Shift('T02', 2025);
        $this->assertEquals('T02', $newShift->name);
    }

    /**
     * Test shift year can be retrieved
     */
    public function testShiftYearCanBeRetrieved()
    {
        $this->assertEquals(2024, $this->shift->year);
        
        $newShift = new \Shift('T01', 2025);
        $this->assertEquals(2025, $newShift->year);
    }

    /**
     * Test __toString returns shift name
     */
    public function testToStringReturnsShiftName()
    {
        $this->assertEquals('T01', (string)$this->shift);
        
        $newShift = new \Shift('Turno_A', 2024);
        $this->assertEquals('Turno_A', (string)$newShift);
    }

    /**
     * Test shift name with various formats
     */
    public function testShiftNameWithVariousFormats()
    {
        $shifts = ['T01', 'T02', 'T03', 'Turno A', 'Practical_1', 'P1-2024'];
        
        foreach ($shifts as $name) {
            $shift = new \Shift($name, 2024);
            $this->assertEquals($name, $shift->name);
            $this->assertEquals($name, (string)$shift);
        }
    }

    /**
     * Test multiple shifts can exist independently
     */
    public function testMultipleShiftsAreIndependent()
    {
        $shift1 = new \Shift('T01', 2024);
        $shift2 = new \Shift('T02', 2024);
        
        $prof1 = new \User('prof1', 'Prof 1', 'prof1@example.com', '', ROLE_PROF, false);
        $prof2 = new \User('prof2', 'Prof 2', 'prof2@example.com', '', ROLE_PROF, false);
        
        $shift1->prof = $prof1;
        $shift2->prof = $prof2;
        
        $this->assertEquals('prof1', $shift1->prof->id);
        $this->assertEquals('prof2', $shift2->prof->id);
    }

    /**
     * Test addGroup method adds group to collection
     */
    public function testAddGroupMethodAddsGroupToCollection()
    {
        $group = $this->createMock(\ProjGroup::class);
        
        $this->shift->addGroup($group);
        
        $this->assertEquals(1, $this->shift->groups->count());
        $this->assertTrue($this->shift->groups->contains($group));
    }

    /**
     * Test addGroup can be called multiple times
     */
    public function testAddGroupCanBeCalledMultipleTimes()
    {
        $group1 = $this->createMock(\ProjGroup::class);
        $group2 = $this->createMock(\ProjGroup::class);
        $group3 = $this->createMock(\ProjGroup::class);
        
        $this->shift->addGroup($group1);
        $this->shift->addGroup($group2);
        $this->shift->addGroup($group3);
        
        $this->assertEquals(3, $this->shift->groups->count());
    }
}

<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;



/**
 * Test suite for FinalGrade entity
 * 
 * Tests final grade configuration with year and formula
 */
class FinalGradeTest extends UnitTestCase
{
    /**
     * Test final grade can be instantiated
     */
    public function testFinalGradeCanBeCreated()
    {
        $finalGrade = new \FinalGrade();
        $this->assertInstanceOf(\FinalGrade::class, $finalGrade);
    }

    /**
     * Test final grade year can be set and retrieved
     */
    public function testFinalGradeYearCanBeSetAndRetrieved()
    {
        $finalGrade = new \FinalGrade();
        $finalGrade->year = 2024;
        
        $this->assertEquals(2024, $finalGrade->year);
    }

    /**
     * Test final grade formula can be set and retrieved
     */
    public function testFinalGradeFormulaCanBeSetAndRetrieved()
    {
        $finalGrade = new \FinalGrade();
        $formula = '0.3 * milestone_avg + 0.4 * patch_avg + 0.3 * participation';
        $finalGrade->formula = $formula;
        
        $this->assertEquals($formula, $finalGrade->formula);
    }

    /**
     * Test final grade supports complex formulas
     */
    public function testFinalGradeSupportsComplexFormulas()
    {
        $finalGrade = new \FinalGrade();
        $complexFormula = 'max(0, min(20, sum(grades) / count(grades) + bonus - penalties))';
        $finalGrade->formula = $complexFormula;
        
        $this->assertStringContainsString('max', $finalGrade->formula);
        $this->assertStringContainsString('min', $finalGrade->formula);
        $this->assertStringContainsString('sum', $finalGrade->formula);
    }

    /**
     * Test multiple final grades for different years
     */
    public function testMultipleFinalGradesForDifferentYears()
    {
        $finalGrade2024 = new \FinalGrade();
        $finalGrade2024->year = 2024;
        $finalGrade2024->formula = '0.5 * test1 + 0.5 * test2';
        
        $finalGrade2025 = new \FinalGrade();
        $finalGrade2025->year = 2025;
        $finalGrade2025->formula = '0.3 * test1 + 0.3 * test2 + 0.4 * final';
        
        $this->assertEquals(2024, $finalGrade2024->year);
        $this->assertEquals(2025, $finalGrade2025->year);
        $this->assertNotEquals($finalGrade2024->formula, $finalGrade2025->formula);
    }

    /**
     * Test empty formula
     */
    public function testFinalGradeWithEmptyFormula()
    {
        $finalGrade = new \FinalGrade();
        $finalGrade->year = 2024;
        $finalGrade->formula = '';
        
        $this->assertEquals('', $finalGrade->formula);
    }

    /**
     * Test formula with special characters
     */
    public function testFormulaWithSpecialCharacters()
    {
        $finalGrade = new \FinalGrade();
        $formula = 'sum(a * 1.5, b - 0.2, c + 10%) / 3';
        $finalGrade->formula = $formula;
        
        $this->assertEquals($formula, $finalGrade->formula);
    }
}

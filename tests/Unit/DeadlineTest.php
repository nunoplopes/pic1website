<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;



/**
 * Test suite for Deadline entity
 * 
 * Tests deadline dates, active status checking for each milestone
 */
class DeadlineTest extends UnitTestCase
{
    private \Deadline $deadline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deadline = new \Deadline(2024);
    }

    /**
     * Test that deadline can be instantiated with year
     */
    public function testDeadlineCanBeCreated()
    {
        $this->assertInstanceOf(\Deadline::class, $this->deadline);
        $this->assertEquals(2024, $this->deadline->year);
    }

    /**
     * Test deadline has all required date fields
     */
    public function testDeadlineHasAllDateFields()
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->deadline->proj_proposal);
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->deadline->bug_selection);
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->deadline->feature_selection);
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->deadline->patch_submission);
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->deadline->final_report);
    }

    /**
     * Test proj_proposal active status when deadline is in future
     */
    public function testProjProposalIsActiveFuture()
    {
        $future = new \DateTimeImmutable('+1 day');
        $this->deadline->proj_proposal = $future;
        
        $this->assertTrue($this->deadline->isProjProposalActive());
    }

    /**
     * Test proj_proposal inactive when deadline is in past
     */
    public function testProjProposalIsInactivePast()
    {
        $past = new \DateTimeImmutable('-1 day');
        $this->deadline->proj_proposal = $past;
        
        $this->assertFalse($this->deadline->isProjProposalActive());
    }

    /**
     * Test bug_selection active status when deadline is in future
     */
    public function testBugSelectionIsActiveFuture()
    {
        $future = new \DateTimeImmutable('+1 day');
        $this->deadline->bug_selection = $future;
        
        $this->assertTrue($this->deadline->isBugSelectionActive());
    }

    /**
     * Test bug_selection inactive when deadline is in past
     */
    public function testBugSelectionIsInactivePast()
    {
        $past = new \DateTimeImmutable('-1 day');
        $this->deadline->bug_selection = $past;
        
        $this->assertFalse($this->deadline->isBugSelectionActive());
    }

    /**
     * Test feature_selection active status when deadline is in future
     */
    public function testFeatureSelectionIsActiveFuture()
    {
        $future = new \DateTimeImmutable('+1 day');
        $this->deadline->feature_selection = $future;
        
        $this->assertTrue($this->deadline->isFeatureSelectionActive());
    }

    /**
     * Test feature_selection inactive when deadline is in past
     */
    public function testFeatureSelectionIsInactivePast()
    {
        $past = new \DateTimeImmutable('-1 day');
        $this->deadline->feature_selection = $past;
        
        $this->assertFalse($this->deadline->isFeatureSelectionActive());
    }

    /**
     * Test patch_submission active status when deadline is in future
     */
    public function testPatchSubmissionIsActiveFuture()
    {
        $future = new \DateTimeImmutable('+1 day');
        $this->deadline->patch_submission = $future;
        
        $this->assertTrue($this->deadline->isPatchSubmissionActive());
    }

    /**
     * Test patch_submission inactive when deadline is in past
     */
    public function testPatchSubmissionIsInactivePast()
    {
        $past = new \DateTimeImmutable('-1 day');
        $this->deadline->patch_submission = $past;
        
        $this->assertFalse($this->deadline->isPatchSubmissionActive());
    }

    /**
     * Test final_report active status when deadline is in future
     */
    public function testFinalReportIsActiveFuture()
    {
        $future = new \DateTimeImmutable('+1 day');
        $this->deadline->final_report = $future;
        
        $this->assertTrue($this->deadline->isFinalReportActive());
    }

    /**
     * Test final_report inactive when deadline is in past
     */
    public function testFinalReportIsInactivePast()
    {
        $past = new \DateTimeImmutable('-1 day');
        $this->deadline->final_report = $past;
        
        $this->assertFalse($this->deadline->isFinalReportActive());
    }

    /**
     * Test deadline year can be set and retrieved
     */
    public function testDeadlineYearCanBeSetAndRetrieved()
    {
        $deadline2025 = new \Deadline(2025);
        $this->assertEquals(2025, $deadline2025->year);
        
        $deadline2026 = new \Deadline(2026);
        $this->assertEquals(2026, $deadline2026->year);
    }

    /**
     * Test multiple deadlines can exist independently
     */
    public function testMultipleDeadlinesAreIndependent()
    {
        $deadline2024 = new \Deadline(2024);
        $deadline2025 = new \Deadline(2025);
        
        $deadline2024->proj_proposal = new \DateTimeImmutable('+1 day');
        $deadline2025->proj_proposal = new \DateTimeImmutable('-1 day');
        
        $this->assertTrue($deadline2024->isProjProposalActive());
        $this->assertFalse($deadline2025->isProjProposalActive());
    }
}

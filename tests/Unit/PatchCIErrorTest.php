<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;



/**
 * Test suite for PatchCIError entity
 * 
 * Tests CI error tracking for patch build failures
 */
class PatchCIErrorTest extends UnitTestCase
{
    private \Patch $patch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->patch = $this->createMock(\Patch::class);
    }

    /**
     * Test patch CI error can be instantiated with required parameters
     */
    public function testPatchCIErrorCanBeInstantiated()
    {
        $now = new \DateTimeImmutable();
        $ciError = new \PatchCIError($this->patch, 'abc123', 'TestError', 'https://ci.example.com/error', $now);
        $this->assertInstanceOf(\PatchCIError::class, $ciError);
    }

    /**
     * Test patch CI error has patch relationship
     */
    public function testPatchCIErrorHasPatchRelationship()
    {
        $now = new \DateTimeImmutable();
        $ciError = new \PatchCIError($this->patch, 'abc123', 'TestError', 'https://ci.example.com/error', $now);
        
        $this->assertEquals($this->patch, $ciError->patch);
    }

    /**
     * Test patch CI error has hash
     */
    public function testPatchCIErrorHasHash()
    {
        $now = new \DateTimeImmutable();
        $hash = 'abc123def456';
        $ciError = new \PatchCIError($this->patch, $hash, 'TestError', 'https://ci.example.com/error', $now);
        
        $this->assertEquals($hash, $ciError->hash);
    }

    /**
     * Test patch CI error has name
     */
    public function testPatchCIErrorHasName()
    {
        $now = new \DateTimeImmutable();
        $name = 'CompilationError';
        $ciError = new \PatchCIError($this->patch, 'abc123', $name, 'https://ci.example.com/error', $now);
        
        $this->assertEquals($name, $ciError->name);
    }

    /**
     * Test patch CI error has URL
     */
    public function testPatchCIErrorHasUrl()
    {
        $now = new \DateTimeImmutable();
        $url = 'https://ci.example.com/build/12345/error';
        $ciError = new \PatchCIError($this->patch, 'abc123', 'TestError', $url, $now);
        
        $this->assertEquals($url, $ciError->url);
    }

    /**
     * Test patch CI error has time
     */
    public function testPatchCIErrorHasTime()
    {
        $now = new \DateTimeImmutable();
        $ciError = new \PatchCIError($this->patch, 'abc123', 'TestError', 'https://ci.example.com/error', $now);
        
        $this->assertEquals($now, $ciError->time);
    }

    /**
     * Test patch CI error can get commit URL from patch
     */
    public function testPatchCIErrorCanGetCommitUrl()
    {
        $now = new \DateTimeImmutable();
        $expectedUrl = 'https://github.com/owner/repo/commit/abc123';
        
        // Mock the patch to return the expected commit URL
        $mockPatch = $this->createMock(\Patch::class);
        $mockPatch->expects($this->once())
            ->method('getCommitURL')
            ->with('abc123')
            ->willReturn($expectedUrl);
        
        $ciError = new \PatchCIError($mockPatch, 'abc123', 'TestError', 'https://ci.example.com/error', $now);
        
        $this->assertEquals($expectedUrl, $ciError->getCommitURL());
    }

    /**
     * Test multiple CI errors can be tracked
     */
    public function testMultipleCIErrorsCanBeTracked()
    {
        $time1 = new \DateTimeImmutable('-2 hours');
        $error1 = new \PatchCIError($this->patch, 'abc123', 'Error1', 'https://ci.example.com/1', $time1);
        
        $time2 = new \DateTimeImmutable('-1 hour');
        $error2 = new \PatchCIError($this->patch, 'def456', 'Error2', 'https://ci.example.com/2', $time2);
        
        $this->assertEquals('abc123', $error1->hash);
        $this->assertEquals('def456', $error2->hash);
        $this->assertNotEquals($error1->time, $error2->time);
    }

    /**
     * Test patch CI error timestamp ordering
     */
    public function testPatchCIErrorTimestampOrdering()
    {
        $error1 = new \PatchCIError(
            $this->patch,
            'hash1',
            'Error1',
            'https://ci.example.com/1',
            new \DateTimeImmutable('2024-01-01 10:00:00')
        );
        
        $error2 = new \PatchCIError(
            $this->patch,
            'hash2',
            'Error2',
            'https://ci.example.com/2',
            new \DateTimeImmutable('2024-01-01 11:00:00')
        );
        
        $this->assertLessThan($error2->time, $error1->time);
    }
}

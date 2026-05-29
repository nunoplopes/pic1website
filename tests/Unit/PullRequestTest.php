<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;



/**
 * Test suite for PullRequest entity
 * 
 * Tests pull request abstract class structure
 */
class PullRequestTest extends UnitTestCase
{
    /**
     * Test pull request is abstract
     */
    public function testPullRequestIsAbstract()
    {
        $reflection = new \ReflectionClass(\PullRequest::class);
        $this->assertTrue($reflection->isAbstract());
    }

    /**
     * Test pull request has repository property
     */
    public function testPullRequestHasRepositoryProperty()
    {
        $reflection = new \ReflectionClass(\PullRequest::class);
        $this->assertTrue($reflection->hasProperty('repository'));
    }

    /**
     * Test pull request has required abstract methods
     */
    public function testPullRequestHasRequiredAbstractMethods()
    {
        $reflection = new \ReflectionClass(\PullRequest::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_ABSTRACT);
        
        $methodNames = array_map(fn($m) => $m->getName(), $methods);
        
        $this->assertContains('url', $methodNames);
        $this->assertContains('branchURL', $methodNames);
        $this->assertContains('origin', $methodNames);
        $this->assertContains('isClosed', $methodNames);
        $this->assertContains('wasMerged', $methodNames);
        $this->assertContains('mergedBy', $methodNames);
        $this->assertContains('mergeDate', $methodNames);
        $this->assertContains('linesAdded', $methodNames);
        $this->assertContains('linesDeleted', $methodNames);
        $this->assertContains('filesModified', $methodNames);
        $this->assertContains('failedCIjobs', $methodNames);
    }

    /**
     * Test pull request can be extended
     */
    public function testPullRequestCanBeExtended()
    {
        // Create anonymous implementation of abstract class for testing
        $prImplementation = new class extends \PullRequest {
            public function url(): string {
                return 'https://github.com/example/repo/pull/123';
            }
            
            public function branchURL(): string {
                return 'https://github.com/example/repo/tree/feature-branch';
            }
            
            public function origin(): string {
                return 'github';
            }
            
            public function isClosed(): bool {
                return false;
            }
            
            public function wasMerged(): bool {
                return false;
            }
            
            public function mergedBy(): string {
                return '';
            }
            
            public function mergeDate(): \DateTimeImmutable {
                return new \DateTimeImmutable();
            }
            
            public function linesAdded(): int {
                return 100;
            }
            
            public function linesDeleted(): int {
                return 50;
            }
            
            public function filesModified(): int {
                return 5;
            }
            
            public function failedCIjobs(string $hash): array {
                return [];
            }
            
            public function __toString() {
                return '#123';
            }
        };
        
        $this->assertInstanceOf(\PullRequest::class, $prImplementation);
        $this->assertEquals('https://github.com/example/repo/pull/123', $prImplementation->url());
        $this->assertEquals('github', $prImplementation->origin());
        $this->assertFalse($prImplementation->isClosed());
    }
}

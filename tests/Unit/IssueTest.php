<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;



/**
 * Test suite for Issue entity
 * 
 * Tests abstract Issue factory pattern for creating issue instances
 */
class IssueTest extends UnitTestCase
{
    /**
     * Test Issue factory returns GitHubIssue for valid GitHub URL
     */
    public function testIssueFactoryReturnsGitHubIssueForValidUrl()
    {
        $validGitHubUrl = 'https://github.com/owner/repo/issues/123';
        $result = \Issue::factory($validGitHubUrl);
        
        $this->assertInstanceOf(\GitHub\GitHubIssue::class, $result);
    }

    /**
     * Test Issue factory returns null for invalid URL
     */
    public function testIssueFactoryReturnsNullForInvalidUrl()
    {
        $result = \Issue::factory('https://example.com/invalid');
        
        $this->assertNull($result);
    }

    /**
     * Test Issue is abstract and cannot be instantiated directly
     */
    public function testIssueIsAbstract()
    {
        $reflection = new \ReflectionClass(\Issue::class);
        $this->assertTrue($reflection->isAbstract());
    }

    /**
     * Test Issue has required abstract methods
     */
    public function testIssueHasRequiredAbstractMethods()
    {
        $reflection = new \ReflectionClass(\Issue::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_ABSTRACT);
        
        $methodNames = array_map(fn($m) => $m->getName(), $methods);
        
        $this->assertContains('getTitle', $methodNames);
        $this->assertContains('getDescription', $methodNames);
    }

    /**
     * Test Issue factory is static
     */
    public function testIssueFactoryIsStatic()
    {
        $reflection = new \ReflectionClass(\Issue::class);
        $method = $reflection->getMethod('factory');
        
        $this->assertTrue($method->isStatic());
    }
}

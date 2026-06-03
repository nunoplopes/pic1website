<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;
use Tests\Mocks\FakePatch;
use Tests\Mocks\FakePullRequest;
use Tests\Mocks\FakeQueryResultEntityManager;



// Explicitly require Patch.php to load enums
require_once dirname(__DIR__, 2) . '/entities/Patch.php';

/**
 * Test suite for Patch entity and enums
 * 
 * Tests PatchStatus enum, PatchType enum, and the complex Patch factory method
 */
class PatchTest extends UnitTestCase
{
    /**
     * Test PatchStatus enum values
     */
    public function testPatchStatusEnumValues()
    {
        $this->assertEquals(0, \PatchStatus::WaitingReview->value);
        $this->assertEquals(1, \PatchStatus::Reviewed->value);
        $this->assertEquals(2, \PatchStatus::Approved->value);
        $this->assertEquals(3, \PatchStatus::PROpen->value);
        $this->assertEquals(4, \PatchStatus::PROpenIllegal->value);
        $this->assertEquals(5, \PatchStatus::Merged->value);
        $this->assertEquals(6, \PatchStatus::MergedIllegal->value);
        $this->assertEquals(7, \PatchStatus::NotMerged->value);
        $this->assertEquals(8, \PatchStatus::NotMergedIllegal->value);
        $this->assertEquals(9, \PatchStatus::Closed->value);
    }

    /**
     * Test PatchStatus enum labels
     */
    public function testPatchStatusEnumLabels()
    {
        $this->assertEquals('waiting review', \PatchStatus::WaitingReview->label());
        $this->assertEquals('reviewed (not approved)', \PatchStatus::Reviewed->label());
        $this->assertEquals('approved', \PatchStatus::Approved->label());
        $this->assertEquals('PR open', \PatchStatus::PROpen->label());
        $this->assertEquals('PR open wo/ approval', \PatchStatus::PROpenIllegal->label());
        $this->assertEquals('merged', \PatchStatus::Merged->label());
        $this->assertEquals('merged wo/ approval', \PatchStatus::MergedIllegal->label());
        $this->assertEquals('closed, not merged', \PatchStatus::NotMerged->label());
        $this->assertEquals('closed, not merged wo/ approval', \PatchStatus::NotMergedIllegal->label());
        $this->assertEquals('closed', \PatchStatus::Closed->label());
    }

    /**
     * Test PatchType enum values
     */
    public function testPatchTypeEnumValues()
    {
        $this->assertEquals(0, \PatchType::BugFix->value);
        $this->assertEquals(1, \PatchType::Feature->value);
    }

    /**
     * Test PatchType enum labels
     */
    public function testPatchTypeEnumLabels()
    {
        $this->assertEquals('bug fix', \PatchType::BugFix->label());
        $this->assertEquals('feature', \PatchType::Feature->label());
    }

    /**
     * Test Patch factory throws exception when group has no repository
     */
    public function testPatchFactoryThrowsExceptionWhenGroupHasNoRepository()
    {
        $this->expectException(\ValidationException::class);
        $this->expectExceptionMessage('Group has no repository yet');

        $shift = new \Shift('T01', 2024);
        $group = new \ProjGroup(1, 2024, $shift);
        $user = new \User(
            username: 'testuser',
            name: 'Test User',
            email: 'test@example.com',
            photo: '',
            role: ROLE_STUDENT,
            dummy: false
        );

        // Group has no repository, so factory should throw
        \Patch::factory($group, 'https://github.com/test/repo/compare/main...test:branch:feature',
                       \PatchType::Feature, 'Test patch', $user);
    }

    /**
     * Test PatchStatus default value
     */
    public function testPatchStatusDefaultValue()
    {
        $patch = $this->createConcretePatchMock();

        $this->assertSame(\PatchStatus::WaitingReview, $patch->status);
    }

    /**
     * Test that Patch class is abstract
     */
    public function testPatchIsAbstract()
    {
        $reflection = new \ReflectionClass(\Patch::class);
        $this->assertTrue($reflection->isAbstract());
    }

    /**
     * Test Patch factory is static
     */
    public function testPatchFactoryMethodIsStatic()
    {
        $reflection = new \ReflectionClass(\Patch::class);
        $method = $reflection->getMethod('factory');
        
        $this->assertTrue($method->isStatic());
    }

    /**
     * Test DONT_WANT_ISSUE_IN_COMMIT_MSG constant is defined
     */
    public function testDontWantIssueInCommitMsgConstantExists()
    {
        $this->assertTrue(defined('DONT_WANT_ISSUE_IN_COMMIT_MSG'));
        
        $constant = constant('DONT_WANT_ISSUE_IN_COMMIT_MSG');
        $this->assertIsArray($constant);
        
        // Verify expected entries exist
        $this->assertArrayHasKey('github:ArduPilot/ardupilot', $constant);
        $this->assertArrayHasKey('github:godotengine/godot', $constant);
        $this->assertArrayHasKey('github:oppia/oppia', $constant);
    }

    /**
     * Test concrete Patch methods with mock implementation
     */
    public function testPatchGetStatusReturnsLabel()
    {
        $patch = $this->createConcretePatchMock();
        $patch->status = \PatchStatus::Approved;
        
        $this->assertEquals('approved', $patch->getStatus());
    }

    /**
     * Test Patch getType returns label
     */
    public function testPatchGetTypeReturnsLabel()
    {
        $patch = $this->createConcretePatchMock();
        $patch->type = \PatchType::BugFix;
        
        $this->assertEquals('bug fix', $patch->getType());
    }

    /**
     * Test Patch isStillOpen returns true for pending statuses
     */
    public function testPatchIsStillOpenForPendingStatus()
    {
        $patch = $this->createConcretePatchMock();
        $patch->status = \PatchStatus::Approved;
        
        $this->assertTrue($patch->isStillOpen());
    }

    /**
     * Test Patch isStillOpen returns false for merged status
     */
    public function testPatchIsNotStillOpenForMergedStatus()
    {
        $patch = $this->createConcretePatchMock();
        $patch->status = \PatchStatus::Merged;
        
        $this->assertFalse($patch->isStillOpen());
    }

    /**
     * Test Patch wasMerged returns true for merged status
     */
    public function testPatchWasMergedReturnsTrueForMergedStatus()
    {
        $patch = $this->createConcretePatchMock();
        $patch->status = \PatchStatus::Merged;
        
        $this->assertTrue($patch->wasMerged());
    }

    /**
     * Test Patch wasMerged returns true for merged illegal status
     */
    public function testPatchWasMergedReturnsTrueForMergedIllegalStatus()
    {
        $patch = $this->createConcretePatchMock();
        $patch->status = \PatchStatus::MergedIllegal;
        
        $this->assertTrue($patch->wasMerged());
    }

    /**
     * Test Patch wasMerged returns false for unmerged status
     */
    public function testPatchWasMergedReturnsFalseForUnmergedStatus()
    {
        $patch = $this->createConcretePatchMock();
        $patch->status = \PatchStatus::NotMerged;
        
        $this->assertFalse($patch->wasMerged());
    }

    /**
     * Test Patch getPRURL returns null when no PR
     */
    public function testPatchGetPRUrlReturnsNullWhenNoPR()
    {
        $patch = $this->createConcretePatchMock();
        
        $this->assertNull($patch->getPRURL());
    }

    /**
     * Test Patch constructor initializes collections
     */
    public function testPatchConstructorInitializesCollections()
    {
        $patch = $this->createConcretePatchMock();
        
        $this->assertInstanceOf(\Doctrine\Common\Collections\ArrayCollection::class, $patch->comments);
        $this->assertInstanceOf(\Doctrine\Common\Collections\ArrayCollection::class, $patch->ci_failures);
        $this->assertInstanceOf(\Doctrine\Common\Collections\ArrayCollection::class, $patch->students);
        $this->assertTrue($patch->comments->isEmpty());
        $this->assertTrue($patch->ci_failures->isEmpty());
        $this->assertTrue($patch->students->isEmpty());
    }

    /**
     * Helper to create a concrete Patch implementation for testing
     */
    private function createConcretePatchMock(): \Patch
    {
        return new FakePatch(patchCommits: [
            ['username' => 'user1', 'name' => 'User One', 'email' => 'user1@example.com', 'message' => 'First commit', 'co-authored' => []],
            ['username' => 'user2', 'name' => 'User Two', 'email' => 'user2@example.com', 'message' => 'Second commit', 'co-authored' => [['user1', 'User One', 'user1@example.com'], ['user3', 'User Three', 'user3@example.com']]],
        ]);
    }

    /**
     * Test Patch allAuthors returns list of unique authors from commits
     */
    public function testPatchAllAuthorsReturnsUniqueCommitAuthors()
    {
        $patch = $this->createConcretePatchMock();
        $authors = $patch->allAuthors();

        $this->assertSame([
            ['user1', 'User One', 'user1@example.com'],
            ['user2', 'User Two', 'user2@example.com'],
            ['user3', 'User Three', 'user3@example.com'],
        ], array_values($authors));
    }

    /**
     * Test Patch factory is static and public
     */
    public function testPatchFactoryMethodSignature()
    {
        $reflection = new \ReflectionClass(\Patch::class);
        $method = $reflection->getMethod('factory');
        
        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
        
        // Check parameters
        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(4, count($params));
    }

    /**
     * Test Patch getIssueURL for feature type
     */
    public function testPatchGetIssueURLForFeatureType()
    {
        $shift = new \Shift('T01', 2024);
        $group = new \ProjGroup(1, 2024, $shift);
        $group->url_proposal = 'https://github.com/example/repo/issues/1';
        
        $patch = $this->createConcretePatchMock();
        $patch->group = $group;
        $patch->type = \PatchType::Feature;
        
        $this->assertEquals('https://github.com/example/repo/issues/1', $patch->getIssueURL());
    }

    /**
     * Test Patch getIssue returns null for feature type without proposal
     */
    public function testPatchGetIssueReturnsNullForFeatureTypeWithoutProposal()
    {
        $shift = new \Shift('T01', 2024);
        $group = new \ProjGroup(1, 2024, $shift);
        $group->url_proposal = ''; // Empty string, not null
        
        $patch = $this->createConcretePatchMock();
        $patch->group = $group;
        $patch->type = \PatchType::Feature;
        
        $result = $patch->getIssue();
        // Empty string should result in null
        $this->assertNull($result);
    }

    /**
     * Test Patch getSubmitter returns user from first comment
     */
    public function testPatchGetSubmitterReturnsUserFromFirstComment()
    {
        $user = new \User(
            username: 'testuser',
            name: 'Test User',
            email: 'test@example.com',
            photo: '',
            role: ROLE_STUDENT,
            dummy: false
        );
        
        $patch = $this->createConcretePatchMock();
        $comment = new \PatchComment($patch, 'Test comment', $user);
        $patch->comments->add($comment);
        
        $this->assertEquals($user, $patch->getSubmitter());
    }

    /**
     * Test Patch getSubmitterName returns submitter's short name
     */
    public function testPatchGetSubmitterNameReturnsShortName()
    {
        $user = new \User(
            username: 'testuser',
            name: 'Test User',
            email: 'test@example.com',
            photo: '',
            role: ROLE_STUDENT,
            dummy: false
        );
        
        $patch = $this->createConcretePatchMock();
        $comment = new \PatchComment($patch, 'Test comment', $user);
        $patch->comments->add($comment);
        
        $this->assertEquals($user->shortName(), $patch->getSubmitterName());
    }

    /**
     * Test Patch getHashes returns all hashes including current
     */
    public function testPatchGetHashesReturnsAllHashes()
    {
        $user = new \User(
            username: 'testuser',
            name: 'Test User',
            email: 'test@example.com',
            photo: '',
            role: ROLE_STUDENT,
            dummy: false
        );
        
        $patch = $this->createConcretePatchMock();
        $patch->hash = 'current_hash_123';
        
        // Add comment with hash reference
        $comment = new \PatchComment($patch, 'New branch hash: old_hash_456', $user);
        $patch->comments->add($comment);
        
        $hashes = $patch->getHashes();
        
        $this->assertContains('old_hash_456', $hashes);
        $this->assertContains('current_hash_123', $hashes);
    }

    /**
     * Test Patch addCIError adds error to ci_failures collection
     */
    public function testPatchAddCIErrorAddsToCollection()
    {
        $patch = $this->createConcretePatchMock();
        $time = new \DateTimeImmutable();
        
        $patch->addCIError('abc123', 'TestError', 'https://ci.example.com/error', $time);
        
        $this->assertTrue($patch->ci_failures->count() === 1);
        
        $error = $patch->ci_failures->first();
        $this->assertInstanceOf(\PatchCIError::class, $error);
        $this->assertEquals('abc123', $error->hash);
        $this->assertEquals('TestError', $error->name);
    }

    /**
     * Test Patch addCIError prevents duplicates
     */
    public function testPatchAddCIErrorPreventsDuplicates()
    {
        $patch = $this->createConcretePatchMock();
        $time = new \DateTimeImmutable();
        
        $patch->addCIError('abc123', 'TestError', 'https://ci.example.com/error1', $time);
        $patch->addCIError('abc123', 'TestError', 'https://ci.example.com/error2', $time);
        
        // Should still be 1 error, not 2
        $this->assertTrue($patch->ci_failures->count() === 1);
    }

    /**
     * Test Patch set_video_url stores valid URL
     */
    public function testPatchSetVideoUrlStoresValidUrl()
    {
        $patch = $this->createConcretePatchMock();
        
        // Test with empty URL (should be valid)
        $patch->set_video_url('');
        $this->assertEquals('', $patch->video_url);
    }

    /**
     * Test Patch updateStats handles valid patch
     */
    public function testPatchUpdateStatsWithValidPatch()
    {
        $shift = new \Shift('T01', 2024);
        $group = new \ProjGroup(1, 2024, $shift);
        
        $patch = $this->createConcretePatchMock();
        $patch->group = $group;
        $patch->status = \PatchStatus::WaitingReview;
        
        // updateStats should work without errors for valid patch
        $patch->updateStats();
        
        // Hash should be computed
        $this->assertEquals('abc123', $patch->hash);
    }

    public function testPatchAddReviewCommentReturnsNullWhenHashAlreadyReviewed()
    {
        $patch = $this->createConcretePatchMock();
        $patch->hash = 'abc123';
        $patch->comments->add(new \PatchComment(
            $patch,
            "🤖 AI-generated feedback — please review carefully\nCommit: abc123\n\nAlready reviewed"
        ));

        $this->assertNull($patch->add_patch_review_comment());
        $this->assertCount(1, $patch->comments);
    }

    public function testPatchUpdateStatsUsesPullRequestData()
    {
        $patch = $this->createPatchWithState(true, $this->createPullRequestMock(true, false));
        $patch->status = \PatchStatus::Approved;

        $patch->updateStats();

        $this->assertEquals(\PatchStatus::Merged, $patch->status);
        $this->assertEquals(12, $patch->lines_added);
        $this->assertEquals(3, $patch->lines_deleted);
        $this->assertEquals(2, $patch->files_modified);
    }

    public function testPatchUpdateStatsUsesClosedPullRequestData()
    {
        $patch = $this->createPatchWithState(true, $this->createPullRequestMock(false, true));
        $patch->status = \PatchStatus::WaitingReview;

        $patch->updateStats();

        $this->assertEquals(\PatchStatus::NotMergedIllegal, $patch->status);
        $this->assertEquals(12, $patch->lines_added);
        $this->assertEquals(3, $patch->lines_deleted);
        $this->assertEquals(2, $patch->files_modified);
    }

    public function testPatchUpdateStatsHandlesDeletedBranchStatuses()
    {
        $patch = $this->createPatchWithState(false);
        $patch->status = \PatchStatus::PROpenIllegal;
        $patch->updateStats();
        $this->assertEquals(\PatchStatus::NotMergedIllegal, $patch->status);
        $this->assertEquals(0, $patch->lines_added);

        $patch = $this->createPatchWithState(false);
        $patch->status = \PatchStatus::PROpen;
        $patch->updateStats();
        $this->assertEquals(\PatchStatus::NotMerged, $patch->status);

        $patch = $this->createPatchWithState(false);
        $patch->status = \PatchStatus::Reviewed;
        $patch->updateStats();
        $this->assertEquals(\PatchStatus::Closed, $patch->status);
    }

    public function testPatchGetIssueURLForBugFixType()
    {
        global $entityManager;
        $oldEntityManager = $entityManager ?? null;

        $bug = new \SelectedBug();
        $bug->issue_url = 'https://github.com/mockorg/mockrepo/issues/7';
        $entityManager = new FakeQueryResultEntityManager($bug);

        $shift = new \Shift('T01', 2024);
        $group = new \ProjGroup(1, 2024, $shift);
        $user = new \User('testuser', 'Test User', 'test@example.com', '', ROLE_STUDENT, false);

        $patch = $this->createConcretePatchMock();
        $patch->group = $group;
        $patch->type = \PatchType::BugFix;
        $patch->comments->add(new \PatchComment($patch, 'Patch submitted', $user));

        try {
            $this->assertEquals('https://github.com/mockorg/mockrepo/issues/7', $patch->getIssueURL());
        } finally {
            $entityManager = $oldEntityManager;
        }
    }

    private function createPatchWithState(bool $valid, ?\PullRequest $pr = null): \Patch
    {
        return new FakePatch(valid: $valid, pullRequest: $pr);
    }

    private function createPullRequestMock(bool $merged, bool $closed): \PullRequest
    {
        return new FakePullRequest(
            branchUrl: 'https://github.com/mockorg/mockrepo/tree/feature',
            pullRequestOrigin: 'mockorg:mockrepo:feature',
            closed: $closed,
            merged: $merged,
            merger: 'mockuser',
            addedLines: 12,
            deletedLines: 3,
            modifiedFiles: 2
        );
    }

}

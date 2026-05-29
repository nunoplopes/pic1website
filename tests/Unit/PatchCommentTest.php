<?php

namespace Tests\Unit;

use Tests\Unit\Base\UnitTestCase;



/**
 * Test suite for PatchComment entity
 * 
 * Tests patch comments with user references and timestamps
 */
class PatchCommentTest extends UnitTestCase
{
    private \Patch $patch;
    private \User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock patch for testing
        $this->patch = $this->createMock(\Patch::class);
        
        // Create a test user
        $this->user = new \User(
            username: 'reviewer1',
            name: 'Code Reviewer',
            email: 'reviewer@example.com',
            photo: '',
            role: ROLE_PROF,
            dummy: false
        );
    }

    /**
     * Test patch comment can be created with text and user
     */
    public function testPatchCommentCanBeCreatedWithTextAndUser()
    {
        $commentText = 'This implementation looks great!';
        $comment = new \PatchComment($this->patch, $commentText, $this->user);
        
        $this->assertInstanceOf(\PatchComment::class, $comment);
        $this->assertEquals($commentText, $comment->text);
        $this->assertEquals($this->user, $comment->user);
        $this->assertEquals($this->patch, $comment->patch);
    }

    /**
     * Test patch comment can be created without user
     */
    public function testPatchCommentCanBeCreatedWithoutUser()
    {
        $commentText = 'Automatic CI comment';
        $comment = new \PatchComment($this->patch, $commentText);
        
        $this->assertEquals($commentText, $comment->text);
        $this->assertNull($comment->user);
        $this->assertEquals($this->patch, $comment->patch);
    }

    /**
     * Test patch comment sets timestamp on creation
     */
    public function testPatchCommentSetsTimestampOnCreation()
    {
        $before = new \DateTimeImmutable();
        $comment = new \PatchComment($this->patch, 'Test comment', $this->user);
        $after = new \DateTimeImmutable();
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $comment->time);
        $this->assertGreaterThanOrEqual($before, $comment->time);
        $this->assertLessThanOrEqual($after, $comment->time);
    }

    /**
     * Test patch comment text can be very long
     */
    public function testPatchCommentTextCanBeVeryLong()
    {
        $longText = str_repeat('This is a long comment. ', 1000);
        $comment = new \PatchComment($this->patch, $longText, $this->user);
        
        $this->assertEquals($longText, $comment->text);
    }

    /**
     * Test patch comment text can contain special characters
     */
    public function testPatchCommentTextCanContainSpecialCharacters()
    {
        $specialText = 'Comment with special chars: @#$%^&*()_+-=[]{}|;:,.<>?/`~';
        $comment = new \PatchComment($this->patch, $specialText, $this->user);
        
        $this->assertEquals($specialText, $comment->text);
    }

    /**
     * Test patch comment text can contain code snippets
     */
    public function testPatchCommentTextCanContainCodeSnippets()
    {
        $codeText = <<<'EOT'
```php
public function testExample() {
    $this->assertTrue(true);
}
```
EOT;
        $comment = new \PatchComment($this->patch, $codeText, $this->user);
        
        $this->assertStringContainsString('php', $comment->text);
        $this->assertStringContainsString('function', $comment->text);
    }

    /**
     * Test patch comment can be edited
     */
    public function testPatchCommentCanBeEdited()
    {
        $originalText = 'Original comment';
        $comment = new \PatchComment($this->patch, $originalText, $this->user);
        
        $newText = 'Updated comment';
        $comment->text = $newText;
        
        $this->assertEquals($newText, $comment->text);
    }

    /**
     * Test patch comment user can be changed
     */
    public function testPatchCommentUserCanBeChanged()
    {
        $comment = new \PatchComment($this->patch, 'Test', $this->user);
        
        $newUser = new \User('user2', 'Another User', 'user2@example.com', '', ROLE_STUDENT, false);
        $comment->user = $newUser;
        
        $this->assertEquals($newUser, $comment->user);
        $this->assertEquals('user2', $comment->user->id);
    }

    /**
     * Test patch comment with null user initially
     */
    public function testPatchCommentWithNullUserInitially()
    {
        $comment = new \PatchComment($this->patch, 'Automated comment');
        $this->assertNull($comment->user);
        
        // Can assign user later
        $comment->user = $this->user;
        $this->assertEquals($this->user, $comment->user);
    }

    /**
     * Test multiple comments on one patch each receive a creation timestamp
     */
    public function testMultipleCommentsReceiveCreationTimestamps()
    {
        $before = new \DateTimeImmutable();
        $comment1 = new \PatchComment($this->patch, 'First comment', $this->user);
        $comment2 = new \PatchComment($this->patch, 'Second comment', $this->user);
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $comment1->time);
        $this->assertLessThanOrEqual($after, $comment1->time);
        $this->assertGreaterThanOrEqual($before, $comment2->time);
        $this->assertLessThanOrEqual($after, $comment2->time);
    }

    /**
     * Test patch comment empty text
     */
    public function testPatchCommentWithEmptyText()
    {
        $comment = new \PatchComment($this->patch, '', $this->user);
        
        $this->assertEquals('', $comment->text);
    }
}

<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'review.php';

use Doctrine\ORM\Mapping as ORM;

enum PatchStatus : int {
  case WaitingReview = 0;
  case Reviewed = 1;
  case Approved = 2;
  case PROpen = 3;
  case PROpenIllegal = 4;
  case Merged = 5;
  case MergedIllegal = 6;
  case NotMerged = 7;
  case NotMergedIllegal = 8;
  case Closed = 9;

  public function label(): string {
    return match ($this) {
      self::WaitingReview    => 'waiting review',
      self::Reviewed         => 'reviewed (not approved)',
      self::Approved         => 'approved',
      self::PROpen           => 'PR open',
      self::PROpenIllegal    => 'PR open wo/ approval',
      self::Merged           => 'merged',
      self::MergedIllegal    => 'merged wo/ approval',
      self::NotMerged        => 'closed, not merged',
      self::NotMergedIllegal => 'closed, not merged wo/ approval',
      self::Closed           => 'closed',
    };
  }
}

enum PatchType : int {
  case BugFix = 0;
  case Feature = 1;

  public function label(): string {
    return match ($this) {
      self::BugFix  => 'bug fix',
      self::Feature => 'feature',
    };
  }
}

define('DONT_WANT_ISSUE_IN_COMMIT_MSG', [
  'github:ArduPilot/ardupilot' => 'https://ardupilot.org/dev/docs/submitting-patches-back-to-master.html#preparing-commits',
  'github:oppia/oppia' => 'https://github.com/oppia/oppia/wiki/Make-a-pull-request#step-2-make-commits-locally-to-your-feature-branch',
]);


#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_branch_per_group', columns: ['group_id', 'repo_branch'])]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'platform', type: 'string')]
#[ORM\DiscriminatorMap(['github' => 'GitHub\GitHubPatch'])]
abstract class Patch
{
  #[ORM\Id]
  #[ORM\Column]
  #[ORM\GeneratedValue]
  public int $id;

  #[ORM\ManyToOne(targetEntity: "ProjGroup", inversedBy: "patches")]
  #[ORM\JoinColumn(nullable: false)]
  public ProjGroup $group;

  #[ORM\Column]
  public PatchStatus $status = PatchStatus::WaitingReview;

  #[ORM\Column]
  public PatchType $type;

  #[ORM\OneToMany(targetEntity: "PatchComment", mappedBy: "patch", cascade: ["persist", "remove"])]
  #[ORM\OrderBy(["id" => "ASC"])]
  public $comments;

  #[ORM\OneToMany(targetEntity: "PatchCIError", mappedBy: "patch", cascade: ["persist", "remove"])]
  #[ORM\OrderBy(["time" => "ASC"])]
  public $ci_failures;

  #[ORM\ManyToMany(targetEntity: "User")]
  public $students;

  #[ORM\Column(length: 64)]
  public string $hash = '';

  #[ORM\Column]
  public string $video_url = '';

  #[ORM\Column]
  public int $lines_added;

  #[ORM\Column]
  public int $lines_deleted;

  #[ORM\Column]
  public int $files_modified;

  static function factory(ProjGroup $group, string $url, PatchType $type,
                          string $description, User $submitter,
                          string $video_url= '',
                          bool $ignore_errors = false) : Patch {
    $repo = $group->getRepository();
    if (!$repo)
      throw new ValidationException('Group has no repository yet');

    $p        = GitHub\GitHubPatch::construct($url, $repo);
    $p->group = $group;
    $p->type  = $type;

    try {
      $p->updateStats();
    } catch (Exception $ex) {
      throw new ValidationException('Patch not found');
    }

    if (!$p->isValid())
      throw new ValidationException('Patch not found');

    $description = trim($description);
    $p->comments->add(
      new PatchComment($p, "Patch submitted; hash: {$p->hash}\n\n$description",
                       $submitter));

    try {
      $p->set_video_url($video_url);

      if (!$description)
        throw new ValidationException("Empty description");

      if (empty($p->students))
        throw new ValidationException("Patch has no recognized authors");

      if (in_array($p->branch(), ['main', 'master', 'develop']))
        throw new ValidationException('Invalid branch name: ' . $p->branch().
                                      "\nPlease use a different branch name");

      $commits = $p->commits();

      if (count($commits) == 0)
        throw new ValidationException('No commit found in the given branch');

      foreach ($group->patches as $old_patch) {
        if ($p->origin() == $old_patch->origin())
          throw new ValidationException('Duplicated patch');
      }

      foreach ($p->diff() as $diff) {
        if (preg_match('/^\+(.*)\s+\n$/Sm', $diff['patch'], $m)) {
          throw new ValidationException('File ' . $diff['filename'] .
                                        ' has trailing whitespace: ' . $m[0]);
        }

        if (str_ends_with($diff['patch'], "\n\\ No newline at end of file")) {
          throw new ValidationException('File ' . $diff['filename'] .
                                        ' is missing a newline at the end');
        }
      }

      foreach ($commits as $commit) {
        if (!check_email($commit['email']))
          throw new ValidationException(
            'Invalid email used in commit: ' . $commit['email']);

        $msg = $commit['message'];
        if (str_starts_with($msg, 'Merge branch '))
          throw new ValidationException('Merge commits are not allowed');

        if ($group->dco && !preg_match('/^Signed-off-by: .* <[^>]+>$/mS', $msg))
          throw new ValidationException(
            "Missing signature in a project with DCO.");

        if (empty($commit['co-authored']) &&
            preg_match('/Co[- ]*authored[- ]*by\s*:.*/Si', $msg, $m))
          throw new ValidationException("Invalid Co-authored-by line:\n$m[0]");

        if (!empty($commit['co-authored'])) {
          $coauthor = false;
          foreach (explode("\n", trim($msg)) as $line) {
            if (str_starts_with($line, 'Co-authored-by:')) {
              $coauthor = true;
            } elseif ($coauthor) {
              throw new ValidationException("Co-authored-by lines must be at ".
                                            "the end\n$msg");
            }
          }
        }

        if (strlen($msg) < 32)
          throw new ValidationException("Commit message is too short");

        $small_lines = 0;
        $total_lines = 0;
        foreach (explode("\n", $msg) as $line) {
          $len = strlen($line);
          if ($len > 1 && $len < 32)
            ++$small_lines;
          ++$total_lines;
        }
        if ($small_lines / $total_lines > 0.5)
          throw new ValidationException(
            "Most lines of the commit message are too short. ".
            "The limit is 72 characters per line.");

        check_reasonable_name($commit['name'], $group);
        check_wrapped_commit_text($msg, 72);
      }

      if ($url_exception = DONT_WANT_ISSUE_IN_COMMIT_MSG[$repo->id] ?? '') {
        foreach ($commits as $commit) {
          if (preg_match('/Fix(?:es)? #/i', $commit['message'])) {
            throw new ValidationException(
              "Commit message references an issue, but it shouldn't per the ".
              "project's guidelines:\n$url_exception\n\n" .
              $commit['message']);
          }
        }
      }

      $issue_url = $p->getIssueURL();

      if ($p->type == PatchType::BugFix) {
        if (count($commits) != 1)
          throw new ValidationException('Only 1 commit allowed');

        if (!$issue_url)
          throw new ValidationException('Patch does not have a bug associated');

        if (!$url_exception && preg_match('/\d/', $issue_url)) {
          if (!preg_match('/Fix(?:es)? #(\d+)/i', $commits[0]['message'], $m))
            throw new ValidationException(
              "Commit message doesn't reference the fixed issue properly:\n" .
              $commits[0]['message']);

          if (!str_contains($issue_url, $m[1]))
            throw new ValidationException(
              "Referenced issue #$m[1] doesn't match the specified issue URL: ".
              $issue_url);
        }
      } elseif ($issue_url) {
        $found_ref = false;
        foreach ($commits as $commit) {
          if (preg_match('/#(\d+)/', $commit['message'], $m) &&
              str_contains($issue_url, $m[1])) {
            $found_ref = true;
            break;
          }
        }
        if (!$found_ref)
          throw new ValidationException(
            'No commit message references the issue #id');
      }
    } catch (ValidationException $ex) {
      if (!$ignore_errors)
        throw $ex;
      $p->comments->add(
        new PatchComment($p, "Failed validation:\n" . $ex->getMessage()));
    }
    $p->add_patch_review_comment();
    return $p;
  }

  function add_patch_review_comment() : ?string {
    // check if the latest commit already has an AI review
    foreach ($this->comments as $c) {
      if (str_starts_with($c->text, "🤖 AI-generated feedback") &&
          str_contains($c->text, "Commit: " . $this->hash))
        return null;
    }

    $description = explode("\n\n", $this->comments->first()->text, 2)[1] ?? '';
    $issue_description = '';
    if ($issue = $this->getIssue()) {
      $issue_description = $issue->getTitle() . "\n" . $issue->getDescription();
    }
    try {
      $review = review_patch($this->group->project_name,
                             $this->type == PatchType::BugFix,
                             $this->patch(), $description, $this->getIssueURL(),
                             $issue_description, $this->group->coding_style);
      $this->comments->add(
        new PatchComment($this,
                         "🤖 AI-generated feedback — please review carefully\n".
                         "Commit: " . $this->hash . "\n\n" . $review));
      return $review;
    } catch (ValidationException $ex) {
      // the AI service isn't very reliable; ignore errors
    }
    return null;
  }

  public function __construct() {
    $this->comments    = new \Doctrine\Common\Collections\ArrayCollection();
    $this->ci_failures = new \Doctrine\Common\Collections\ArrayCollection();
    $this->students    = new \Doctrine\Common\Collections\ArrayCollection();
  }

  abstract public function isValid() : bool;
  abstract public function branch() : string;
  abstract public function origin() : string;
  abstract public function commits() : array;
  abstract public function diff() : array;
  abstract public function patch() : string;
  abstract protected function computeBranchHash() : string;
  abstract protected function computeLinesAdded() : int;
  abstract protected function computeLinesDeleted() : int;
  abstract protected function computeFilesModified() : int;
  abstract public function getPatchURL() : string;
  abstract public function getCommitURL(string $hash) : string;
  abstract public function setPR(PullRequest $pr);
  abstract public function findAndSetPR() : bool;
  abstract public function getPR() : ?PullRequest;

  public function updateStats() {
    $isvalid = $this->isValid();
    $this->hash = $isvalid ? $this->computeBranchHash() : '';

    if ($pr = $this->getPR()) {
      $legal = in_array($this->status,
                        [PatchStatus::PROpen, PatchStatus::Approved]);
      if ($pr->wasMerged()) {
        $this->status
          = $legal ? PatchStatus::Merged : PatchStatus::MergedIllegal;
      } else if ($pr->isClosed()) {
        $this->status
          = $legal ? PatchStatus::NotMerged : PatchStatus::NotMergedIllegal;
      }
      $this->lines_added    = $pr->linesAdded();
      $this->lines_deleted  = $pr->linesDeleted();
      $this->files_modified = $pr->filesModified();

      // Can't update author data here as github doesn't give us that data for
      // PRs. Since the branch may be deleted by now, the info is lost.

      return;
    }

    // check if branch was deleted in the meantime
    if (!$isvalid) {
      if ($this->status == PatchStatus::PROpenIllegal) {
        $this->status = PatchStatus::NotMergedIllegal;
      } elseif ($this->status == PatchStatus::PROpen) {
        $this->status = PatchStatus::NotMerged;
      } elseif ($this->status->value < PatchStatus::Approved->value) {
        $this->status = PatchStatus::Closed;
      }
      $this->lines_added    = 0;
      $this->lines_deleted  = 0;
      $this->files_modified = 0;
      return;
    }

    // Branch is valid, but no PR yet
    $this->lines_added    = $this->computeLinesAdded();
    $this->lines_deleted  = $this->computeLinesDeleted();
    $this->files_modified = $this->computeFilesModified();

    $this->students->clear();
    foreach ($this->allAuthors() as $author) {
      $login = $author[0];
      $name  = $author[1];
      $email = $author[2];
      if ($this->students->contains($login))
        continue;

      foreach ($this->group->students as $student) {
        $repou = $student->getRepoUser();
        if (($repou && $login == $repou->username()) ||
            $email == $student->email ||
            has_similar_name($student->name, $name)) {
          $this->students->add($student);
          break;
        }
      }
    }
  }

  public function getPRURL() {
    $pr = $this->getPR();
    return $pr ? $pr->url() : null;
  }

  public function getIssueURL() : ?string {
    if ($this->type == PatchType::Feature)
      return $this->group->url_proposal;

    $bug = db_fetch_bug_user($this->group->year, $this->getSubmitter());
    return $bug === null ? null : $bug->issue_url;
  }

  public function getIssue() : ?Issue {
    $url = $this->getIssueURL();
    return $url ? Issue::factory($url) : null;
  }

  /// returns (login, name, email)*
  public function allAuthors() {
    $authors = [];
    foreach ($this->commits() as $commit) {
      $authors[] = [$commit['username'], $commit['name'], $commit['email']];
      $authors = array_merge($authors, $commit['co-authored']);
    }
    return array_unique($authors, SORT_REGULAR);
  }

  public function getStatus() {
    return $this->status->label();
  }

  public function getType() {
    return $this->type->label();
  }

  public function isStillOpen() {
    return $this->status->value < PatchStatus::Merged->value;
  }

  public function wasMerged() {
    return $this->status == PatchStatus::Merged ||
           $this->status == PatchStatus::MergedIllegal;
  }

  public function getSubmitter() : User {
    return $this->comments[0]->user;
  }

  public function getSubmitterName() : string {
    return $this->getSubmitter()->shortName();
  }

  public function getHashes() {
    $hashes = [];
    foreach ($this->comments as $comment) {
      if (preg_match('/New branch hash: (\S+)/', $comment->text, $m))
        $hashes[] = $m[1];
    }
    $hashes[] = $this->hash;
    return $hashes;
  }

  public function addCIError($hash, $name, $url, $time) {
    foreach ($this->ci_failures as $error) {
      if ($error->hash == $hash && $error->name == $name)
        return;
    }
    $this->ci_failures->add(new PatchCIError($this, $hash, $name, $url, $time));
  }

  function set_video_url(string $url) {
    if ($url && !get_video_info($url)) {
      throw new ValidationException('Video URL not recognized as a video');
    }
    $this->video_url = check_url($url);
  }
}

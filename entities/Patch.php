<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

define('PATCH_WAITING_REVIEW', 0);
define('PATCH_REVIEWED', 1);
define('PATCH_APPROVED', 2);
define('PATCH_PR_OPEN', 3);
define('PATCH_PR_OPEN_ILLEGAL', 4);
define('PATCH_MERGED', 5);
define('PATCH_MERGED_ILLEGAL', 6);
define('PATCH_NOTMERGED', 7);
define('PATCH_NOTMERGED_ILLEGAL', 8);

define('PATCH_BUGFIX', 0);
define('PATCH_FEATURE', 1);

define('DONT_WANT_ISSUE_IN_COMMIT_MSG', [
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
  public int $status = PATCH_WAITING_REVIEW;

  #[ORM\Column]
  public int $type;

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

  static function factory(ProjGroup $group, string $url, $type,
                          string $description, User $submitter,
                          string $video_url= '',
                          bool $ignore_errors = false) : Patch {
    $repo = $group->getRepository();
    if (!$repo)
      throw new ValidationException('Group has no repository yet');

    $p        = GitHub\GitHubPatch::construct($url, $repo);
    $p->group = $group;
    $p->type  = (int)$type;

    try {
      $p->updateStats();
    } catch (Exception $ex) {
      throw new ValidationException('Patch not found');
    }

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

      if ($p->type < PATCH_BUGFIX || $p->type > PATCH_FEATURE)
        throw new ValidationException('Unknown patch type');

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

      foreach ($commits as $commit) {
        if (!check_email($commit['email']))
          throw new ValidationException(
            'Invalid email used in commit: ' . $commit['email']);

        $msg = $commit['message'];
        if (str_starts_with($msg, 'Merge branch '))
          throw new ValidationException('Merge commits are not allowed');

        if (empty($commit['co-authored']) &&
            preg_match('/Co[- ]*authored[- ]*by\s*:.*/Si', $msg, $m))
          throw new ValidationException("Invalid Co-authored-by line:\n$m[0]");

        if (!empty($commit['co-authored'])) {
          $coauthor = false;
          foreach (explode("\n", trim($msg)) as $line) {
            if (str_starts_with($line, 'Co-authored-by:')) {
              $coauthor = true;
            } else if ($coauthor) {
              throw new ValidationException("Co-authored-by lines must be at ".
                                            "the end\n$msg");
            }
          }
        }

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

      if ($p->type == PATCH_BUGFIX) {
        if (count($commits) != 1)
          throw new ValidationException('Only 1 commit allowed');

        $issue_url = $p->getIssueURL();
        if (!$issue_url)
          throw new ValidationException('Patch does not have a bug associated');

        if (preg_match('/\d/', $issue_url)) {
          if (!preg_match('/Fix(?:es)? #(\d+)/i', $commits[0]['message'], $m))
            throw new ValidationException(
              "Commit message doesn't reference the fixed issue properly:\n" .
              $commits[0]['message']);

          if (!str_contains($issue_url, $m[1]))
            throw new ValidationException(
              "Referenced issue #$m[1] doesn't match the specified issue URL: ".
              $issue_url);
        }
      }
    } catch (ValidationException $ex) {
      if (!$ignore_errors)
        throw $ex;
      $p->comments->add(
        new PatchComment($p, "Failed validation:\n" . $ex->getMessage()));
    }

    return $p;
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
  abstract protected function computeBranchHash() : string;
  abstract protected function computeLinesAdded() : int;
  abstract protected function computeLinesDeleted() : int;
  abstract protected function computeFilesModified() : int;
  abstract public function getPatchURL() : string;
  abstract public function getCommitURL(string $hash) : string;
  abstract public function setPR(PullRequest $pr);
  abstract public function getPR() : ?PullRequest;

  public function updateStats() {
    $this->hash = $this->computeBranchHash();

    if ($pr = $this->getPR()) {
      $legal = $this->status == PATCH_PR_OPEN;
      if ($pr->wasMerged()) {
        $this->status = $legal ? PATCH_MERGED : PATCH_MERGED_ILLEGAL;
      } else if ($pr->isClosed()) {
        $this->status = $legal ? PATCH_NOTMERGED : PATCH_NOTMERGED_ILLEGAL;
      }
      $this->lines_added    = $pr->linesAdded();
      $this->lines_deleted  = $pr->linesDeleted();
      $this->files_modified = $pr->filesModified();

      // Can't update author data here as github doesn't give us that data for
      // PRs. Since the branch may be deleted by now, the info is lost.

      return;
    }

    // check if branch was deleted in the meantime
    if (!$this->isValid()) {
      if ($this->status == PATCH_PR_OPEN_ILLEGAL) {
        $this->status = PATCH_NOTMERGED_ILLEGAL;
      } else if ($this->status <= PATCH_PR_OPEN) {
        $this->status = PATCH_NOTMERGED;
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
    if ($this->type != PATCH_BUGFIX)
      return null;

    $bug = db_fetch_bug_user($this->group->year, $this->getSubmitter());
    return $bug === null ? null : $bug->issue_url;
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

  static function get_status_options() {
    return [
      PATCH_WAITING_REVIEW    => 'waiting review',
      PATCH_REVIEWED          => 'reviewed',
      PATCH_APPROVED          => 'approved',
      PATCH_PR_OPEN           => 'PR open',
      PATCH_PR_OPEN_ILLEGAL   => 'PR open wo/ approval',
      PATCH_MERGED            => 'merged',
      PATCH_MERGED_ILLEGAL    => 'merged wo/ approval',
      PATCH_NOTMERGED         => 'closed, not merged',
      PATCH_NOTMERGED_ILLEGAL => 'closed, not merged wo/ approval',
    ];
  }

  public function getStatus() {
    return self::get_status_options()[$this->status];
  }

  static function get_type_options() {
    return [
      PATCH_BUGFIX  => 'bug fix',
      PATCH_FEATURE => 'feature',
    ];
  }

  public function getType() {
    return self::get_type_options()[$this->type];
  }

  public function isStillOpen() {
    return $this->status < PATCH_MERGED;
  }

  public function wasMerged() {
    return $this->status == PATCH_MERGED ||
           $this->status == PATCH_MERGED_ILLEGAL;
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

  public function set_status($status) {
    $status = (int)$status;
    if (!isset(self::get_status_options()[$status]))
      throw new ValidationException('invalid status');
    $this->status = $status;
  }

  public function set_type($type) {
    $type = (int)$type;
    if (!isset(self::get_type_options()[$type]))
      throw new ValidationException('invalid type');
    $this->type = $type;
  }

  function set_video_url(string $url) {
    if ($url && !get_video_info($url)) {
      throw new ValidationException('Video URL not recognized as a video');
    }
    $this->video_url = check_url($url);
  }
}

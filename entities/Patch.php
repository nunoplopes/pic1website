<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\ManyToMany;

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


/** @Entity
 *  @InheritanceType("SINGLE_TABLE")
 *  @DiscriminatorColumn(name="platform", type="string")
 *  @DiscriminatorMap({"github" = "GitHub\GitHubPatch"})
 */
abstract class Patch
{
  /** @Id @Column @GeneratedValue */
  public int $id;

  /** @ManyToOne(inversedBy="patches") */
  public ProjGroup $group;

  /** @Column */
  public int $status = PATCH_WAITING_REVIEW;

  /** @Column */
  public int $type;

  /** @Column */
  public string $issue_url = 'https://...';

  /** @Column(length=2000) */
  public string $description;

  /** @Column(length=1000) */
  public string $review = '';

  /** @ManyToMany(targetEntity="User") */
  public $students;

  /** @ManyToOne */
  public User $submitter;

  /** @Column */
  public int $lines_added;

  /** @Column */
  public int $lines_deleted;

  /** @Column */
  public int $files_modified;

  static function factory(ProjGroup $group, string $url, $type,
                          string $issue_url, string $description,
                          User $submitter) : Patch {
    $repo = $group->getRepository();
    if (!$repo)
      throw new ValidationException('Group has no repository yet');

    $p = GitHub\GitHubPatch::construct($url, $repo);
    $p->group       = $group;
    $p->type        = (int)$type;
    $p->issue_url   = check_url($issue_url);
    $p->description = $description;
    $p->submitter   = $submitter;

    try {
      $p->updateStats();
    } catch (Exception $ex) {
      throw new ValidationException('Patch not found');
    }

    if (empty($p->students))
      throw new ValidationException("Patch has no recognized authors");

    if ($p->type < PATCH_BUGFIX || $p->type > PATCH_FEATURE)
      throw new ValidationException('Unknown patch type');

    foreach ($group->patches as $old_patch) {
      if ($p->origin() == $old_patch->origin())
        throw new ValidationException('Duplicated patch');
    }

    return $p;
  }

  public function __construct() {
    $this->students = new \Doctrine\Common\Collections\ArrayCollection();
  }

  abstract public function isValid() : bool;
  abstract public function origin() : string;
  abstract protected function computeAuthors() : array;
  abstract protected function computeLinesAdded() : int;
  abstract protected function computeLinesDeleted() : int;
  abstract protected function computeFilesModified() : int;
  abstract public function getPatchURL() : string;
  abstract public function setPR(PullRequest $pr);
  abstract public function getPR() : ?PullRequest;

  public function updateStats() {
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
    } else {
      $this->lines_added    = $this->computeLinesAdded();
      $this->lines_deleted  = $this->computeLinesDeleted();
      $this->files_modified = $this->computeFilesModified();

      $this->students->clear();
      foreach ($this->computeAuthors() as $author) {
        $login = $author[0];
        $email = $author[2];
        if ($this->students->contains($login))
          continue;

        foreach ($this->group->students as $student) {
          $repou = $student->getRepoUser();
          if (($repou && $login == $repou->username()) ||
              (!$login && $email == $student->email)) {
            $this->students->add($student);
            break;
          }
        }
      }
    }
  }

  public function getPRURL() {
    $pr = $this->getPR();
    return $pr ? $pr->url() : null;
  }

  /// returns (login, name, email)*
  public function allAuthors() {
    return $this->computeAuthors();
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

  public function set_issue_url($url) { $this->issue_url = check_url($url); }
  public function set_description($txt) { $this->description = $txt; }
  public function set_review($txt) { $this->review = $txt; }
}

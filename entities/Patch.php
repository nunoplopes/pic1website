<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;

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


/** @Entity */
class Patch
{
  /** @Id @Column(type="integer") @GeneratedValue */
  public $id;

  /** @ManyToOne(targetEntity="ProjGroup") */
  public $group;

  /** @Column(type="integer") */
  public $status = PATCH_WAITING_REVIEW;

  /** @Column(type="integer") */
  public $type;

  /** @Column */
  public $patch_url;

  /** @Column(type="integer") */
  public $pr_number = 0;

  /** @ManyToMany(targetEntity="User") */
  public $students;

  /** @Column(length=1000) */
  public $description;

  /** @Column(type="integer") */
  public $lines_added;

  /** @Column(type="integer") */
  public $lines_deleted;

  /** @Column(type="integer") */
  public $num_files;

  /** @Column(length=1000) */
  public $review = '';

  public function __construct($group, $patch_url, $type, $description) {
    $this->group        = $group;
    $this->patch_url    = check_url($patch_url);
    $this->type         = (int)$type;
    $this->description  = $description;
    $this->students     = new \Doctrine\Common\Collections\ArrayCollection();

    $this->updateStats();

    if ($this->type < PATCH_BUGFIX || $this->type > PATCH_FEATURE)
      throw new ValidationException('Unknown patch type');
  }

  public function updateStats() {
    if ($gh = GitHub\parse_patch_url($this->patch_url)) {
      $stats = GitHub\get_patch_stats($gh[0], $gh[1], $gh[2], $gh[3]);
      $this->lines_added   = $stats['added'];
      $this->lines_deleted = $stats['deleted'];
      $this->num_files     = $stats['numMfiles'];

      $this->students->clear();
      foreach ($stats['authors'] as $author) {
        foreach ($this->group->students as $student) {
          if ($author == $student->github_username) {
            $this->students->add($student);
            break;
          }
        }
      }
      if ($this->students->isEmpty())
        throw new ValidationException("Patch has no recognized authors");

      if ($gh[0] !== $this->group->getRepo())
        throw new ValidationException("Patch is not for Project's repository");

      if ($this->pr_number) {
        $repo   = GitHub\parse_repo_url($this->group->repository_url);
        $status = GitHub\pr_status($repo, $this->pr_number);
        $legal  = $this->status == PATCH_PR_OPEN;
        if ($status['merged']) {
          $this->status = $legal ? PATCH_MERGED : PATCH_MERGED_ILLEGAL;
        } else if ($status['closed']) {
          $this->status = $legal ? PATCH_NOTMERGED : PATCH_NOTMERGED_ILLEGAL;
        }
      }
    } else {
      throw new ValidationException('Unsupported patch URL');
    }
  }

  public function getStatus() {
    switch ($this->status) {
      case PATCH_WAITING_REVIEW:    return 'waiting review';
      case PATCH_REVIEWED:          return 'reviewed';
      case PATCH_APPROVED:          return 'approved';
      case PATCH_PR_OPEN:           return 'PR open';
      case PATCH_PR_OPEN_ILLEGAL:   return 'PR open wo/ approval';
      case PATCH_MERGED:            return 'merged';
      case PATCH_MERGED_ILLEGAL:    return 'merged wo/ approval';
      case PATCH_NOTMERGED:         return 'closed, not merged';
      case PATCH_NOTMERGED_ILLEGAL: return 'closed, not merged wo/ approval';
      default: die('Internal error: getStatus');
    }
  }

  public function getType() {
    switch ($this->type) {
      case PATCH_BUGFIX:  return 'bug fix';
      case PATCH_FEATURE: return 'feature';
      default: die('Internal error: getType');
    }
  }

  public function getPatchURL() {
    if ($gh = GitHub\parse_patch_url($this->patch_url)) {
      return GitHub\get_patch_url($gh[0], $gh[1], $gh[2], $gh[3]);
    } else {
      die('Internal error: getPatchURL');
    }
  }

  public function getPatchSource() {
    if ($gh = GitHub\parse_patch_url($this->patch_url)) {
      return "$gh[2]:$gh[3]";
    } else {
      die('Internal error: getPatchURL');
    }
  }

  public function isStillOpen() {
    return $this->status < PATCH_MERGED;
  }
}

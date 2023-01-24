<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToOne;

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
abstract class Patch
{
  /** @Id @Column @GeneratedValue */
  public int $id;

  /** @ManyToOne */
  public ProjGroup $group;

  /** @Column */
  public int $status = PATCH_WAITING_REVIEW;

  /** @Column */
  public int $type;

  /** @Column(length=1000) */
  public string $description;

  /** @Column(length=1000) */
  public string $review = '';

  static function factory($group, $url, $type, $description) : ?Patch {
    if (!$group)
      throw new ValidationException('Group has no repository yet');

    $p = GitHub\Patch::construct($url, $group);
    if (!$p)
      return null;

    $p->group       = $group;
    $p->type        = (int)$type;
    $p->description = $description;

    if (empty($p->students()))
      throw new ValidationException("Patch has no recognized authors");

    if ($p->type < PATCH_BUGFIX || $p->type > PATCH_FEATURE)
      throw new ValidationException('Unknown patch type');

    return $p;
  }

  abstract public function authors() : array;
  abstract public function linesAdded() : int;
  abstract public function linesRemoved() : int;
  abstract public function filesModified() : int;
  abstract public function getURL() : string;
  abstract public function setPR(PullRequest $pr);
  abstract public function getPR() : ?PullRequest;

  public function students() {
    $ret = [];
    foreach ($this->authors() as $login) {
      foreach ($this->group->students as $student) {
        if ($login == $student->repository_user->username) {
          $ret[] = $student;
          break;
        }
      }
    }
    return $ret;
  }

  public function updateStatus() {
    if ($pr = $this->getPR()) {
      $legal = $this->status == PATCH_PR_OPEN;
      if ($pr->wasMerged()) {
        $this->status = $legal ? PATCH_MERGED : PATCH_MERGED_ILLEGAL;
      } else if ($pr->isClosed()) {
        $this->status = $legal ? PATCH_NOTMERGED : PATCH_NOTMERGED_ILLEGAL;
      }
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

  public function isStillOpen() {
    return $this->status < PATCH_MERGED;
  }
}

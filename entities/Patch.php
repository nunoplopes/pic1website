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
  /** @Id @Column(type="integer") @GeneratedValue */
  public $id;

  /** @ManyToOne(targetEntity="ProjGroup", inversedBy="patches") */
  public $group;

  /** @Column(type="integer") */
  public $status = PATCH_WAITING_REVIEW;

  /** @Column(type="integer") */
  public $type;

  /** @Column(length=1000) */
  public $description;

  /** @Column(length=1000) */
  public $review = '';

  /** @ManyToMany(targetEntity="User") */
  public $students;

  /** @Column(type="integer") */
  public $lines_added;

  /** @Column(type="integer") */
  public $lines_deleted;

  /** @Column(type="integer") */
  public $files_modified;

  static function factory(ProjGroup $group, string $url, $type,
                          string $description) : Patch {
    $repo = $group->getRepository();
    if (!$repo)
      throw new ValidationException('Group has no repository yet');

    $p = GitHub\GitHubPatch::construct($url, $repo);
    $p->group       = $group;
    $p->type        = (int)$type;
    $p->description = $description;

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

  abstract public function origin() : string;
  abstract protected function computeAuthors() : array;
  abstract protected function computeLinesAdded() : int;
  abstract protected function computeLinesDeleted() : int;
  abstract protected function computeFilesModified() : int;
  abstract public function getURL() : string;
  abstract public function setPR(PullRequest $pr);
  abstract public function getPR() : ?PullRequest;

  public function updateStats() {
    if ($pr = $this->getPR()) {
      $legal = $this->status == PATCH_PR_OPEN;
      if ($pr->wasMerged()) {
        $this->status = $legal ? PATCH_MERGED : PATCH_MERGED_ILLEGAL;
      } else if ($pr->isClosed()) {
        $this->status = $legal ? PATCH_NOTMERGED : PATCH_NOTMERGED_ILLEGAL;
      }
    }
    $this->lines_added    = $this->computeLinesAdded();
    $this->lines_deleted  = $this->computeLinesDeleted();
    $this->files_modified = $this->computeFilesModified();

    $this->students->clear();
    foreach ($this->computeAuthors() as $login) {
      foreach ($this->group->students as $student) {
        if (($repou = $student->getRepoUser()) &&
            $login == $repou->username()) {
          $this->students->add($student);
          break;
        }
      }
    }
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

  public function set_description($txt) { $this->description = $txt; }
  public function set_review($txt) { $this->review = $txt; }
}

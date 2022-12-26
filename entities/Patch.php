<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;

define('PATCH_WAITING_REVIEW', 0);
define('PATCH_REVIEWED', 1);
define('PATCH_APPROVED', 2);
define('PATCH_PR_OPEN', 3);
define('PATCH_MERGED', 4);

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
  public $url;

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

  public function __construct($group, $url, $type, $description) {
    $this->group        = $group;
    $this->url          = check_url($url);
    $this->type         = (int)$type;
    $this->description  = $description;

    if ($gh = GitHub\parse_patch_url($url)) {
      $stats = GitHub\get_patch_stats($gh[0], $gh[1], $gh[2]);
      $this->lines_added   = $stats['added'];
      $this->lines_deleted = $stats['deleted'];
      $this->num_files     = $stats['numMfiles'];
    } else {
      throw new ValidationException('Unsupported patch URL');
    }

    if ($this->type < PATCH_BUGFIX || $this->type > PATCH_FEATURE)
      throw new ValidationException('Unknown patch type');
  }

  public function getStatus() {
    switch ($this->status) {
      case PATCH_WAITING_REVIEW: return 'waiting review';
      case PATCH_REVIEWED:       return 'reviewed';
      case PATCH_APPROVED:       return 'approved';
      case PATCH_PR_OPEN:        return 'PR open';
      case PATCH_MERGED:         return 'merged';
      default: die('Internal error: getStatus');
    }
  }

  public function getPatchURL() {
    if ($gh = GitHub\parse_patch_url($this->url)) {
      return GitHub\get_patch_url($gh[0], $gh[1], $gh[2]);
    } else {
      die('Internal error: getPatchURL');
    }
  }
}

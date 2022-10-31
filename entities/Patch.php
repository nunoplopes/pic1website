<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

define('PATCH_WAITING_REVIEW', 0);
define('PATCH_REVIEWED', 1);
define('PATCH_APPROVED', 2);
define('PATCH_PR_OPEN', 3);
define('PATCH_MERGED', 4);


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
  public $lines_added;

  /** @Column(type="integer") */
  public $lines_deleted;

  /** @Column(type="integer") */
  public $num_files;

  /** @Column */
  public $url;

  /** @Column(length=1000) */
  public $review = '';

  public function __construct($group) {
    $this->group = $group;
  }
}

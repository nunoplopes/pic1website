<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

define('PATCH_DRAFT', 0);
define('PATCH_FOR_TA', 1);
define('PATCH_PR_OPEN', 2);
define('PATCH_MERGED', 3);


/** @Entity */
class Patch
{
  /** @Id @Column(type="integer") @GeneratedValue */
  public $id;

  /** @ManyToOne(targetEntity="ProjGroup") */
  public $group;

  /** @Column(type="integer") */
  public $status;

  /** @Column(type="integer") */
  public $lines_added;

  /** @Column(type="integer") */
  public $lines_deleted;

  /** @Column(type="integer") */
  public $num_files;

  public function __construct($group) {
    $this->group = $group;
  }
}

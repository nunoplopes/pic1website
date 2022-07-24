<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

/** @Entity */
class User
{
  /** @Id @Column(length=16) */
  public $id;

  /** @Column(nullable=true) */
  public $name;

  /** @Column(type="integer") */
  // TODO: switch to enum with PHP 8
  public $role;

  /** @ManyToMany(targetEntity="ProjGroup", mappedBy="students", cascade={"persist"}) */
  public $groups;

  /** @Column(nullable=true) */
  public $github_username;

  /** @Column(nullable=true) */
  public $github_etag;

  public function __construct() {
    $this->groups = new \Doctrine\Common\Collections\ArrayCollection();
  }
}

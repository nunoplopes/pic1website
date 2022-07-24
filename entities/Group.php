<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

/** @Entity */
class Group
{
  /** @Id @Column(type="integer") @GeneratedValue */
  public $id;

  /** @Column(type="integer") */
  public $group_number;

  /** @Column(type="integer") */
  public $year;

  /** @ManyToMany(targetEntity="User", inversedBy="groups") */
  public $students;

  /** @Column(type="integer") */
  // TODO: switch to enum with PHP 8
  public $provider;

  /** @Column(nullable=true) */
  public $provider_id;

  /** @Column(nullable=true) */
  public $project_name;

  /** @Column(nullable=true) */
  public $project_website;

  /** @Column(nullable=true) */
  public $coding_style;
}

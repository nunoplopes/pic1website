<?php

/** @Entity */
class User
{
  /** @Id @Column(length=16) */
  public $id;

  /** @Column(nullable=true) */
  public $name;

  /** @Column(type="integer") */
  // TODO: switch to enum with PHP 8
  // 0 - Superuser
  // 1 - TA
  // 2 - Student
  public $role;

  /** @ManyToMany(targetEntity="Group", mappedBy="students") */
  public $groups;

  /** @Column(nullable=true) */
  public $github_username;

  /** @Column(nullable=true) */
  public $github_etag;
}

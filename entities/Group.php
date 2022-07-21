<?php

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

  /** @Column(nullable=true) */
  public $project_name;

  /** @Column(nullable=true) */
  public $project_website;
}

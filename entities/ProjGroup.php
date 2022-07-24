<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

define('PROVIDER_GITHUB', 0);

/** @Entity */
class ProjGroup
{
  /** @Id @Column(type="integer") @GeneratedValue */
  public $id;

  /** @Column(type="integer") */
  public $group_number;

  /** @Column(type="integer") */
  public $year;

  /** @ManyToMany(targetEntity="User", inversedBy="groups", cascade={"persist"}) */
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

  public function __construct() {
    $this->students = new \Doctrine\Common\Collections\ArrayCollection();
    $this->provider = PROVIDER_GITHUB;
  }

  public function resetStudents() {
    foreach ($this->students as $student) {
      $student->groups->removeElement($this);
    }
    $this->students->clear();
  }

  public function addStudent($student) {
    assert(!$this->students->contains($student));
    $this->students->add($student);
    $student->groups->add($this);
  }
}

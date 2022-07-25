<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;

/** @Entity */
class ProjGroup
{
  /** @Id @Column(type="integer") @GeneratedValue */
  public $id;

  /** @Column(type="integer") */
  public $group_number;

  /** @Column(type="integer") */
  public $year;

  /** @Column */
  public $shift;

  /** @ManyToMany(targetEntity="User", inversedBy="groups", cascade={"persist"}) */
  public $students;

  /** @Column */
  public $project_name = '';

  /** @Column */
  public $project_description = '';

  /** @Column */
  public $project_website = '';

  // FIXME: repo data

  /** @Column(nullable="yes") @ManyToOne(targetEntity="License") */
  public $license;

  /** @Column(type="boolean") */
  public $cla = false;

  /** @Column */
  public $major_users = '';

  /** @Column(type="integer") */
  public $number_of_commits_last_7_days = 0;

  /** @Column(type="integer") */
  public $number_of_authors_of_those_commits = 0;

  /** @Column(type="integer") */
  public $lines_of_code = 0;

  /** @Column */
  public $main_language = '';

  /** @Column */
  public $coding_style = 'https://...';

  /** @Column */
  public $bugs_for_beginners = 'https://...';

  /** @Column */
  public $project_ideas = 'https://...';

  /** @Column */
  public $student_programs = '';

  /** @Column */
  public $getting_started_manual = 'https://...';

  /** @Column */
  public $developers_manual = 'https://...';

  /** @Column */
  public $testing_manual = 'https://...';

  /** @Column */
  public $developers_mailing_list = 'https://...';

  /** @Column */
  public $patch_submission = 'https://...';

  public function __construct($number, $year, $shift) {
    $this->group_number = $number;
    $this->year         = $year;
    $this->shift        = $shift;
    $this->students     = new \Doctrine\Common\Collections\ArrayCollection();
    $shift->addGroup($this);
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

  public function set_provider($provider) {
//FIXME
    $this->provider = $provider;
  }
}

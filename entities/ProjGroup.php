<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
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
  public $project_website = 'https://...';

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

  /** @Column(nullable="yes") @ManyToOne(targetEntity="ProgLanguage") */
  public $main_language;

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

  public function set_project_name($name) { $this->project_name = $name; }
  public function set_project_description($description) { $this->project_description = $description; }
  public function set_project_website($url) { $this->project_website = check_url($url); }
  public function set_major_users($users) { $this->major_users = $users; }
  public function set_number_of_commits_last_7_days($number) { $this->number_of_commits_last_7_days = (int)$number; }
  public function set_number_of_authors_of_those_commits($number) { $this->number_of_authors_of_those_commits = (int)$number; }
  public function set_lines_of_code($number) { $this->lines_of_code = (int)$number; }
  public function set_coding_style($url) { $this->coding_style = check_url($url); }
  public function set_bugs_for_beginners($url) { $this->bugs_for_beginners = check_url($url); }
  public function set_project_ideas($url) { $this->project_ideas = check_url($url); }
  public function set_student_programs($programs) { $this->student_programs = $programs; }
  public function set_getting_started_manual($url) { $this->getting_started_manual = check_url($url); }
  public function set_developers_manual($url) { $this->developers_manual = check_url($url); }
  public function set_testing_manual($url) { $this->testing_manual = check_url($url); }
  public function set_developers_mailing_list($url) { $this->developers_mailing_list = check_url($url); }
  public function set_patch_submission($url) { $this->patch_submission = check_url($url); }

  public function set_license($license) {
    $license = db_fetch_license($license);
    if (!$license)
      throw new Exception('Unknown license');
    $this->license = $license;
  }

  public function set_main_language($language) {
    $language = db_fetch_prog_language($language);
    if (!$language)
      throw new Exception('Unknown programming language');
    $this->main_language = $language;
  }
}

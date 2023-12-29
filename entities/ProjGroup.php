<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

/** @Entity */
class ProjGroup
{
  /** @Id @Column @GeneratedValue */
  public int $id;

  /** @Column */
  public int $group_number;

  /** @Column */
  public int $year;

  /** @ManyToOne(inversedBy="groups") */
  public Shift $shift;

  /** @ManyToMany(targetEntity="User", inversedBy="groups", cascade={"persist"}) */
  public $students;

  /** @OneToMany(targetEntity="Patch", mappedBy="group") */
  public $patches;

  /** @Column */
  public string $project_name = '';

  /** @Column(length=2000) */
  public string $project_description = '';

  /** @Column */
  public string $project_website = 'https://...';

  /** @Column(length=150) */
  public string $repository = '';

  /** @Column */
  public bool $cla = false;

  /** @Column */
  public bool $clo = false;

  /** @Column */
  public string $major_users = '';

  /** @Column */
  public int $lines_of_code = 0;

  /** @Column */
  public string $coding_style = 'https://...';

  /** @Column */
  public string $bugs_for_beginners = 'https://...';

  /** @Column */
  public string $project_ideas = 'https://...';

  /** @Column(length=1000) */
  public string $student_programs = '';

  /** @Column */
  public string $getting_started_manual = 'https://...';

  /** @Column */
  public string $developers_manual = 'https://...';

  /** @Column */
  public string $testing_manual = 'https://...';

  /** @Column */
  public string $developers_mailing_list = 'https://...';

  /** @Column */
  public string $patch_submission = 'https://...';

  /** @Column */
  public DateTimeImmutable $allow_modifications_date;

  public function __construct($number, $year, Shift $shift) {
    $this->group_number = $number;
    $this->year         = $year;
    $this->shift        = $shift;
    $this->patches      = new \Doctrine\Common\Collections\ArrayCollection();
    $this->students     = new \Doctrine\Common\Collections\ArrayCollection();
    $this->allow_modifications_date = new DateTimeImmutable();
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

  public function getRepository() : ?Repository {
    return $this->repository ? new Repository($this->repository) : null;
  }

  public function prof() : ?User {
    return $this->shift->prof;
  }

  public function __toString() {
    return (string)$this->group_number;
  }

  public function getstr_repository() { return $this->repository ? (string)$this->getRepository() : ''; }
  public function set_project_name($name) { $this->project_name = $name; }
  public function set_project_description($description) { $this->project_description = $description; }
  public function set_project_website($url) { $this->project_website = check_url($url); }
  public function set_major_users($users) { $this->major_users = $users; }
  public function set_repository($url) { $this->repository = Repository::factory($url); }
  public function set_cla($cla) { $this->cla = (bool)$cla; }
  public function set_clo($clo) { $this->clo = (bool)$clo; }
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
  public function set_allow_modifications_date($time) { $this->allow_modifications_date = new DateTimeImmutable($time); }
}

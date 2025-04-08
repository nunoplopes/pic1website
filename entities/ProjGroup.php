<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ProjGroup
{
  #[ORM\Id]
  #[ORM\Column]
  #[ORM\GeneratedValue]
  public int $id;

  #[ORM\Column]
  public int $group_number;

  #[ORM\Column]
  public int $year;

  #[ORM\ManyToOne(inversedBy: 'groups')]
  public Shift $shift;

  #[ORM\ManyToMany(targetEntity: 'User', inversedBy: 'groups', cascade: ['persist'])]
  public $students;

  #[ORM\OneToMany(mappedBy: 'group', targetEntity: 'Patch')]
  public $patches;

  #[ORM\Column]
  public string $project_name = '';

  #[ORM\Column(length: 2000)]
  public string $project_description = '';

  #[ORM\Column]
  public string $project_website = 'https://example.org';

  #[ORM\Column(length: 150)]
  public string $repository = '';

  #[ORM\Column]
  public bool $cla = false;

  #[ORM\Column]
  public bool $dco = false;

  #[ORM\Column]
  public string $major_users = '';

  #[ORM\Column]
  public int $lines_of_code = 0;

  #[ORM\Column]
  public string $coding_style = 'https://example.org';

  #[ORM\Column]
  public string $bugs_for_beginners = 'https://example.org';

  #[ORM\Column]
  public string $project_ideas = 'https://example.org';

  #[ORM\Column(length: 1000)]
  public string $student_programs = '';

  #[ORM\Column]
  public string $getting_started_manual = 'https://example.org';

  #[ORM\Column]
  public string $developers_manual = 'https://example.org';

  #[ORM\Column]
  public string $testing_manual = 'https://example.org';

  #[ORM\Column]
  public string $developers_mailing_list = 'https://example.org';

  #[ORM\Column]
  public string $patch_submission = 'https://example.org';

  #[ORM\Column(length: 40)]
  public string $hash_proposal_file = '';

  #[ORM\Column]
  public string $url_proposal = '';

  #[ORM\Column(length: 40)]
  public string $hash_final_report = '';

  #[ORM\Column]
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

  public function getRepositoryId() : string {
    $repo = $this->getRepository();
    return $repo ? $repo->id : '';
  }

  public function getValidRepository() : ?Repository {
    $repo = $this->getRepository();
    return $repo && $repo->isValid() ? $repo : null;
  }

  public function prof() : ?User {
    return $this->shift->prof;
  }

  public function __toString() {
    return (string)$this->group_number;
  }

  public function set_repository($url) {
    $new_repo = $url ? Repository::factory($url) : '';
    // max 5 groups per repo
    if ($new_repo && $new_repo != $this->repository) {
      if (sizeof(db_fetch_groups_by_repo($this->year, $new_repo)) >= 5) {
        throw new ValidationException(
          'Exceed the maximum number of groups per repository');
      }
    }
    $this->repository = $new_repo;
  }

  public function getstr_repository() { return $this->repository ? (string)$this->getRepository() : ''; }
  public function set_project_website($url) { $this->project_website = check_url($url); }
  public function set_coding_style($url) { $this->coding_style = check_url($url); }
  public function set_bugs_for_beginners($url) { $this->bugs_for_beginners = check_url($url); }
  public function set_project_ideas($url) { $this->project_ideas = check_url($url); }
  public function set_getting_started_manual($url) { $this->getting_started_manual = check_url($url); }
  public function set_developers_manual($url) { $this->developers_manual = check_url($url); }
  public function set_testing_manual($url) { $this->testing_manual = check_url($url); }
  public function set_developers_mailing_list($url) { $this->developers_mailing_list = check_url($url); }
  public function set_patch_submission($url) { $this->patch_submission = check_url($url); }
}

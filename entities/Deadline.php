<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/** @Entity */
class Deadline
{
  /** @Id @Column(type="integer") */
  public $year;

  /** @Column(type="datetime") */
  public $proj_proposal;

  /** @Column(type="datetime") */
  public $patch_submission;

  public function __construct($year) {
    $this->year             = $year;
    $this->proj_proposal    = new DateTimeImmutable();
    $this->patch_submission = new DateTimeImmutable();
  }

  public function isProjProposalActive() {
    return new DateTimeImmutable() <= $this->proj_proposal;
  }

  public function isPatchSubmissionActive() {
    return new DateTimeImmutable() <= $this->patch_submission;
  }

  public function set_proj_proposal($time) { $this->proj_proposal = new DateTimeImmutable($time); }
  public function set_patch_submission($time) { $this->patch_submission = new DateTimeImmutable($time); }
}

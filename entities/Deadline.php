<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/** @Entity */
class Deadline
{
  /** @Id @Column */
  public int $year;

  /** @Column */
  public DateTimeImmutable $proj_proposal;

  /** @Column */
  public DateTimeImmutable $bug_selection;

  /** @Column */
  public DateTimeImmutable $feature_selection;

  /** @Column */
  public DateTimeImmutable $patch_submission;

  /** @Column */
  public DateTimeImmutable $final_report;

  public function __construct($year) {
    $this->year              = $year;
    $this->proj_proposal     = new DateTimeImmutable();
    $this->bug_selection     = new DateTimeImmutable();
    $this->feature_selection = new DateTimeImmutable();
    $this->patch_submission  = new DateTimeImmutable();
    $this->final_report      = new DateTimeImmutable();
  }

  public function isProjProposalActive() {
    return new DateTimeImmutable() <= $this->proj_proposal;
  }

  public function isBugSelectionActive() {
    return new DateTimeImmutable() <= $this->bug_selection;
  }

  public function isFeatureSelectionActive() {
    return new DateTimeImmutable() <= $this->feature_selection;
  }

  public function isPatchSubmissionActive() {
    return new DateTimeImmutable() <= $this->patch_submission;
  }

  public function isFinalReportActive() {
    return new DateTimeImmutable() <= $this->final_report;
  }

  public function set_proj_proposal($time) { $this->proj_proposal = new DateTimeImmutable($time); }
  public function set_bug_selection($time) { $this->bug_selection = new DateTimeImmutable($time); }
  public function set_feature_selection($time) { $this->feature_selection = new DateTimeImmutable($time); }
  public function set_patch_submission($time) { $this->patch_submission = new DateTimeImmutable($time); }
  public function set_final_report($time) { $this->final_report = new DateTimeImmutable($time); }
}

<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Deadline
{
  #[ORM\Id]
  #[ORM\Column]
  public int $year;

  #[ORM\Column]
  public DateTimeImmutable $proj_proposal;

  #[ORM\Column]
  public DateTimeImmutable $bug_selection;

  #[ORM\Column]
  public DateTimeImmutable $feature_selection;

  #[ORM\Column]
  public DateTimeImmutable $patch_submission;

  #[ORM\Column]
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
}

<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;

/** @Entity */
class Deadline
{
  /** @Id @Column(type="integer") */
  public $year;

  /** @Column(type="datetime") */
  public $proj_proposal;

  public function __construct($year) {
    $this->year          = $year;
    $this->proj_proposal = new DateTime();
  }

  public function isProjProposalActive() {
    return new DateTime() <= $this->proj_proposal;
  }

  public function set_proj_proposal($time) { $this->proj_proposal = new DateTime($time); }
}

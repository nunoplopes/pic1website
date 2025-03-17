<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FinalGrade
{
  #[ORM\Id]
  #[ORM\Column]
  public int $year;

  #[ORM\Column]
  public string $formula;
}

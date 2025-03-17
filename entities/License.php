<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class License
{
  #[ORM\Id]
  #[ORM\Column(length: 48)]
  public string $id;

  #[ORM\Column]
  public string $name;

  public function __construct($id, $name) {
    $this->id   = $id;
    $this->name = $name;
  }

  public function __toString() {
    return $this->name;
  }
}

<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Grade
{
  #[ORM\Id]
  #[ORM\ManyToOne]
  public User $user;

  #[ORM\Id]
  #[ORM\ManyToOne]
  public Milestone $milestone;

  #[ORM\Column(nullable: true)]
  public ?int $field1;

  #[ORM\Column(nullable: true)]
  public ?int $field2;

  #[ORM\Column(nullable: true)]
  public ?int $field3;

  #[ORM\Column(nullable: true)]
  public ?int $field4;

  #[ORM\Column]
  public int $late_days = 0;

  #[ORM\PrePersist]
  #[ORM\PreUpdate]
  public function validateFields() {
    for ($i = 1; $i <= 4; ++$i) {
      if ($this->milestone->{"field$i"} !== '') {
        $field = $this->{"field$i"};
        $max = $this->milestone->{"range$i"};
        if ($field !== null && ($field < 0 || $field > $max)) {
          throw new ValidationException("Field $i exceeds allowed range");
        }
      }
    }
    if ($this->late_days < 0) {
      throw new ValidationException("Late days cannot be negative");
    }
  }
}

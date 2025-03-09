<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;

/** @Entity
 *  @HasLifecycleCallbacks
*/
class Grade
{
  /** @Id @ManyToOne */
  public User $user;

  /** @Id @ManyToOne */
  public Milestone $milestone;

  /** @Column(nullable=true) */
  public ?int $field1;

  /** @Column(nullable=true) */
  public ?int $field2;

  /** @Column(nullable=true) */
  public ?int $field3;

  /** @Column(nullable=true) */
  public ?int $field4;

  /** @Column */
  public int $late_days = 0;

  /** @PrePersist
   *  @PreUpdate
   */
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

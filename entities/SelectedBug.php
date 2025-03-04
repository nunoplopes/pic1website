<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

/** @Entity
 *  @Table(name="SelectedBug",
 *    uniqueConstraints={
 *      @UniqueConstraint(name="unique_bug_issue", columns={"year", "issue_url"}),
 *    }
 *  )
 */
class SelectedBug
{
  /** @Id @ManyToOne */
  public User $user;

  /** @Id @Column */
  public int $year;

  /** @Column */
  public string $issue_url = '';

  /** @Column */
  public string $repro_url = '';

  /** @Column(length=4096) */
  public string $description;

  static function factory(ProjGroup $group, User $user, string $description,
                          string $issue_url, string $repro_url) {
    $bug = new SelectedBug();
    $bug->year = $group->year;
    $bug->set_issue_url($issue_url);
    $bug->set_repro_url($repro_url);
    $bug->description = trim($description);
    $bug->user = $user;
    return $bug;
  }

  function set_issue_url(string $url) {
    if (!$url) {
      throw new ValidationException('Issue URL is required');
    }
    if ($url != $this->issue_url &&
        db_fetch_bug_issue($this->year, $url) !== null) {
      throw new ValidationException(
        'This bug has been selected by another student already');
    }
    $this->issue_url = check_url($url);
  }

  function set_repro_url(string $url) {
    if ($url && !get_video_info($url)) {
      throw new ValidationException('Repro URL is not recognized as a video');
    }
    $this->repro_url = check_url($url);
  }
}

<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

class Repository
{
  public string $id;

  public function __construct(string $id) {
    $this->id = $id;
  }

  public function name() {
    return substr($this->id, strpos($this->id, ':')+1);
  }

  public function platform() {
    return substr($this->id, 0, strpos($this->id, ':'));
  }

  private function get($fn) {
    $n = $this->name();
    switch ($this->platform()) {
      case 'github': return GitHub\GitHubRepository::$fn($n);
    }
    assert(false);
  }

  public function isValid() : string {
    return $this->get('isValid');
  }

  public function defaultBranch() : string {
    return $this->get('defaultBranch');
  }

  public function parent() : ?string {
    return $this->get('parent');
  }

  public function language() : string {
    return $this->get('language');
  }

  public function license() : ?License {
    return $this->get('license');
  }

  public function stars() : int {
    return $this->get('stars');
  }

  public function topics() : array {
    return $this->get('topics');
  }

  public function commitsLastMonth() : int {
    return $this->get('commitsLastMonth');
  }

  public function __toString() : string {
    return $this->get('toString');
  }

  static function factory($url) : string {
    if ($name = GitHub\GitHubRepository::parse($url)) {
      $id = "github:$name";
    } else {
      throw new ValidationException('Unsupported URL');
    }

    // check if repo exists
    try {
      (new Repository($id))->defaultBranch();
    } catch (\Exception $ex) {
      throw new ValidationException('Unknown project repository');
    }
    return $id;
  }
}

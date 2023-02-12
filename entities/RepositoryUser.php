<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

class PROpenedEvent
{
  public $pr;
  public $date;

  public function __construct(PullRequest $pr, DateTimeImmutable $date) {
    $this->pr   = $pr;
    $this->date = $date;
  }
}


class RepositoryUser
{
  public $user;

  public function __construct(User $user) {
    $this->user = $user;
  }

  public function id() {
    return $this->user->repository_user;
  }

  static function check($user) {
    $r = new RepositoryUser($user);

    $ps = explode(':', $r->id());
    if (count($ps) != 2)
      throw new ValidationException('Allowed syntax is: provider:username '.
                                    '(e.g., github:johnsmith)');

    if ($r->platform() != 'github')
      throw new ValidationException('unknown platform');

    // check if user exists
    try {
      $r->name();
    } catch (\Exception $ex) {
      throw new ValidationException('user does not exist');
    }
  }

  public function username() {
    return substr($this->id(), strpos($this->id(), ':')+1);
  }

  public function platform() {
    return substr($this->id(), 0, strpos($this->id(), ':'));
  }

  private function get($fn) {
    switch ($this->platform()) {
      case 'github': return GitHub\GitHubUser::$fn($this);
    }
    assert(false);
  }

  public function profileURL() : string {
    return $this->get('profileURL');
  }

  public function name() : ?string {
    return $this->get('name');
  }

  public function email() : ?string {
    return $this->get('email');
  }

  public function company() : ?string {
    return $this->get('company');
  }

  public function location() : ?string {
    return $this->get('location');
  }

  public function getUnprocessedEvents() : array {
    return $this->get('getUnprocessedEvents');
  }

  public function description() {
    $data = [
      "name"     => $this->name(),
      "email"    => $this->email(),
      "company"  => $this->company(),
      "location" => $this->location(),
    ];
    $extra = '';
    foreach ($data as $k => $v) {
      if ($v)
        $extra .= " [$k: $v]";
    }
    return $this->platform() . ": " . $this->username() . $extra;
  }
}

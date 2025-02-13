<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

$user = get_user();
handle_form($user, [], [], ['repository_user']);

if ($u = $user->getRepoUser()) {
  echo "<p>User data:</p><ul>";
  $data = [
    "Username" => '<a style="color: white" href="' . $u->profileURL() . '">' .
                  $u->username() . '</a>',
    "Platform" => $u->platform(),
    "Name"     => $u->name(),
    "Email"    => $u->email(),
    "Company"  => $u->company(),
    "Location" => $u->location(),
  ];
  foreach ($data as $k => $v) {
    if ($v)
      echo "<li><b>$k</b>: $v</li>";
  }
  echo '</ul>';
}

<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header("User profile");

$user = get_user();

mk_box_left_begin();
handle_form($user, [], [], ['repository_user']);
mk_box_end();

if ($u = $user->repository_user) {
  mk_box_right_begin();
  echo "<p>User data:</p><ul>";
  $data = [
    "Username" => $u->username,
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
  mk_box_end();
}

mk_box_end();

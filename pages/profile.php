<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

$user = get_user();
handle_form($user, [], [], ['repository_user']);

if ($u = $user->getRepoUser()) {
  $info_box['title'] = 'User data';
  $info_box['rows'] = [
    "Username" => dolink_ext($u->profileURL(), $u->username()),
    "Platform" => $u->platform(),
    "Name"     => $u->name(),
    "Email"    => $u->email(),
    "Company"  => $u->company(),
    "Location" => $u->location(),
  ];
}

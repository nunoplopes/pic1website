<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

auth_require_at_least(ROLE_SUDO);

if (isset($_GET['newrole'])) {
  $role = (int)$_GET['newrole'];
  if (!validate_role($role, true))
    die('Unknown role');

  $_SESSION['username'] = "ist0000$role";
  $seed = [
    'Jameson',
    'Ryker',
    'Adrian',
    'Avery',
  ][$role];

  auth_set_user(
    db_fetch_or_add_user(
      "ist0000$role", "Dummy $role", $role, "ist0000$role@example.org",
      "https://api.dicebear.com/9.x/notionists-neutral/svg?seed=$seed&lips=variant17",
      true));
}
else if (isset($_GET['username'])) {
  $user = db_fetch_user($_GET['username']);
  if (!$user)
    die('Unknown user');
  auth_set_user($user);
}
else {
  foreach (get_all_roles(false) as $id => $name) {
    $lists["Switch to dummy"][]
      = dolink('impersonate', $name, ['newrole' => $id]);
  }

  foreach (db_get_all_users() as $user) {
    $role = $user->getRole();
    $lists["Impersonate real users"][]
      = dolink('impersonate', "$user->id: $user->name ($role)",
               ['username' => $user->id]);
  }
}

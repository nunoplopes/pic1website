<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

auth_require_at_least(ROLE_SUDO);

if (isset($_GET['newrole'])) {
  $role = (int)$_GET['newrole'];
  if (!validate_role($role, true))
    die('Unknown role');

  $_SESSION['username'] = "ist0000$role";
  auth_set_user(db_fetch_or_add_user("ist0000$role", "Dummy $role", $role));
  html_header('Impersonate');
}
else if (isset($_GET['username'])) {
  $user = db_fetch_user($_GET['username']);
  if (!$user)
    die('Unknown user');
  auth_set_user($user);
  html_header('Impersonate');
}
else {
  html_header('Impersonate');
  echo "<p>Switch to dummy:";
  foreach (get_all_roles(false) as $id => $name) {
    echo " ", dolink('impersonate', $name, ['newrole' => $id]);
  }
  echo "</p>\n";


  echo "<p>Impersonate real users:<br>\n";
  foreach (db_get_all_users() as $user) {
    $args = ['username' => $user->id];
    echo dolink('impersonate', "$user->id: $user->name", $args), "<br>\n";
  }
}

<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

auth_at_least(ROLE_SUDO);

if (isset($_GET['newrole'])) {
  $role = (int)$_GET['newrole'];
  if ($role !== ROLE_PROF && $role !== ROLE_TA && $role !== ROLE_STUDENT)
    die('Unknown role');

  $_SESSION['username'] = "ist0000$role";
  $user = db_fetch_or_add_user("ist0000$role", "Dummy $role", $role);
  html_header('Impersonate');
}
else if (isset($_GET['username'])) {
  $user = db_fetch_user($_GET['username']);
  if (!$user)
    die('Unknown user');
  html_header('Impersonate');
}
else {
  html_header('Impersonate');
  $roles = [
    ROLE_PROF => 'Prof',
    ROLE_TA => 'TA',
    ROLE_STUDENT => 'Student'
  ];
  echo "<p>Switch to dummy:";
  foreach ($roles as $id => $name) {
    echo " ", dolink('impersonate', $name, ['newrole' => $id]);
  }
  echo "</p>\n";


  echo "<p>Impersonate real users:<br>\n";
  foreach (db_get_all_users() as $user) {
    $args = ['username' => $user->id];
    echo dolink('impersonate', "$user->name ($user->id)", $args), "<br>\n";
  }
}

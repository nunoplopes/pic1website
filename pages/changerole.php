<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

auth_require_at_least(ROLE_PROF);

html_header("Change Users' Role");

if (isset($_POST['username']) && isset($_POST['newrole'])) {
  $role = (int)$_POST['newrole'];
  if (!validate_role($role))
    die('Unknown role');

  $user = db_fetch_user($_POST['username']);
  if (!$user)
    die('Unknown user');
  $user->role = $role;
  db_flush();
  echo "<p>Changed role successfully!</p>\n";
}

echo <<<EOF
<form action="index.php?page=changerole" method="post">
<label for="username">Username:</label>
<input type="text" id="username" name="username"><br>
<label for="newrole">New role:</label>
<select id="newrole" name="newrole">
EOF;
foreach (get_all_roles(false) as $id => $name) {
  echo "<option value=\"$id\">$name</option>\n";
}
echo <<<EOF
</select>
<input type="submit">
</form>
EOF;

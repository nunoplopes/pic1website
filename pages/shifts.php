<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

auth_require_at_least(ROLE_PROF);

$year = get_current_year();
$shifts = db_fetch_shifts($year);
$profs = db_get_all_profs(true);

if (isset($_POST['submit'])) {
  foreach ($shifts as $shift) {
    $var = "shift_$shift->id";
    if (!empty($_POST[$var])) {
      $user = db_fetch_user($_POST[$var]);
      if (!$user || !$user->roleAtLeast(ROLE_TA))
        die("Unknown user");
      $shift->prof = $user;
    }
  }
  db_flush();
  echo "<p>Saved!</p>";
}

foreach ($profs as $prof) {
  echo "<td>{$prof->shortName()}</td>";
}

foreach ($shifts as $shift) {
  echo "<tr><td>", htmlspecialchars($shift->name), "</td>";
  foreach ($profs as $prof) {
    $selected = '';
    if ($shift->prof == $prof)
      $selected = ' checked';
    echo "<td><input type=\"radio\" name=\"shift_$shift->id\" value=\"",
         htmlspecialchars($prof->id), "\"$selected></td>";
  }
}

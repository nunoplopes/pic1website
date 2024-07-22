<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Project List');

if (auth_at_least(ROLE_TA)) {
  do_start_form('listprojects');
  $selected_year   = do_year_selector();
  $own_shifts_only = do_bool_selector('Show only own shifts', 'own_shifts');
  $selected_shift  = do_shift_selector($selected_year, $own_shifts_only);
  $selected_repo   = do_repo_selector($selected_year);
  $groups          = do_group_selector($selected_year, $selected_shift,
                                       $own_shifts_only, $selected_repo);
  echo "</form>\n";
} else {
  $groups = get_user()->groups;
}

echo "<p>Groups:</p>\n";
$table = [];
foreach ($groups as $group) {
  $students = [];
  foreach ($group->students as $s) {
    $students[] = "$s->name ($s->id)";
  }
  $group = dolink('listproject', $group->group_number, ['id' => $group->id]);
  $table[] = ['Group' => $group, 'Students' => $students];
}

print_table($table);

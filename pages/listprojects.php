<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

if (auth_at_least(ROLE_TA)) {
  $groups = filter_by(['group', 'year', 'shift', 'own_shifts', 'repo']);
} else {
  $groups = get_user()->groups;
}

$table = [];
foreach ($groups as $group) {
  $students = [];
  foreach ($group->students as $s) {
    $students[] = "$s->name ($s->id)";
  }
  $group = dolink('listproject', $group->group_number, ['id' => $group->id]);
  $table[] = ['Group' => $group, 'Students' => implode("\n", $students)];
}

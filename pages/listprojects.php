<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Project List');

$years = db_get_group_years();
$selected_year = $_POST['year'] ?? ($years[0]['year'] ?? '');
$selected_shift
  = isset($_POST['shift']) ? db_fetch_shift_id($_POST['shift']) : null;
$own_shifts_only = !empty($_POST['own_shifts']);

echo <<<EOF
<form action="index.php?page=listprojects" method="post">
<label for="year">Year:</label>
<select name="year" id="year" onchange='this.form.submit()'>
EOF;

foreach ($years as $year) {
  $year = $year['year'];
  $select = $year == $selected_year ? ' selected' : '';
  echo "<option value=\"$year\"$select>$year/",$year+1,"</option>\n";
}

$own_shifts_checked = $own_shifts_only ? ' checked' : '';

echo <<<EOF
</select>
<br>

<label for="own_shifts">Show only own shifts</label>
<input type="checkbox" id="own_shifts" name="own_shifts" value="1"
onchange='this.form.submit()'$own_shifts_checked>
<br>

<label for="shift">Show specific shift:</label>
<select name="shift" id="shift" onchange='this.form.submit()'>
<option value="all">All</option>
EOF;

foreach (db_fetch_shifts($selected_year) as $shift) {
  if (!has_shift_permissions($shift))
    continue;
  if ($own_shifts_only &&
      get_user()->role != ROLE_STUDENT &&
      $shift->prof != get_user())
    continue;
  $select = $shift == $selected_shift ? ' selected' : '';
  echo "<option value=\"$shift->id\"$select>", htmlspecialchars($shift->name),
       "</option>\n";
}

echo <<<EOF
</select>
</form>
EOF;

echo "<p>Groups:</p>\n";
$table = [];
foreach (db_fetch_groups($selected_year) as $group) {
  if (!has_group_permissions($group))
    continue;

  if ($selected_shift && $group->shift != $selected_shift)
    continue;

  if ($own_shifts_only && $group->prof() != get_user())
    continue;

  $students = [];
  foreach ($group->students as $s) {
    $students[] = "$s->name ($s->id)";
  }
  $group = dolink('listproject', $group->group_number, ['id' => $group->id]);
  $table[] = ['Group' => $group, 'Students' => $students];
}

print_table($table);

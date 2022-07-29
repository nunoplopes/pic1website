<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Project List');

$years = db_get_group_years();
$selected_year = $_GET['year'] ?? $years[0]['year'];

echo <<<EOF
<form action="index.php?page=listprojects" method="post">
<label for="years">Year:</label>
<select name="year" id="years" onchange='this.form.submit()'>
EOF;

foreach ($years as $year) {
  $year = $year['year'];
  $select = $year == $selected_year ? ' selected' : '';
  echo "<option value=\"$year\"$select>$year/",$year+1,"</option>\n";
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

  $students = [];
  foreach ($group->students as $s) {
    $students[] = "$s->name ($s->id)";
  }
  $group = dolink('listproject', $group->group_number, ['id' => $group->id]);
  $table[] = ['Group' => $group, 'Students' => $students];
}

print_table($table);

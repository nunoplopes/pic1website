<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

html_header('Project List');

$years = db_get_group_years();
$selected_year = $_GET['year'] ?? $years[0];

echo <<<EOF
<form action="index.php?page=listprojects" method="post">
<label for="years">Year:</label>
<select name="year" id="years" onchange='this.form.submit()'>
EOF;

foreach ($years as $year) {
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
  if (has_group_permissions($group))
    $table[] = ['id' => $group['id'], 'students' => $group['students']];
}

print_table($table);

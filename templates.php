<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

function html_header($title) {
  $user = get_user();
  $role = get_role_string();

echo <<< EOF
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
table, th, td {
  border: 1px solid
}
</style>
<title>PIC1: $title</title>
</head>
<body>
<p>User: $user->name ($user->id)<br>
Role: $role</p>
EOF;
}

function html_footer() {
  $pages = [
    ['listprojects', 'Display projects', ROLE_STUDENT],
    ['changerole', 'Change Role', ROLE_PROF],
    ['impersonate', 'Impersonate', ROLE_SUDO],
    ['phpinfo', 'PHP Info', ROLE_PROF],
  ];
  echo '<p>';
  foreach ($pages as $page) {
    if (auth_at_least($page[2]))
      echo dolink($page[0], $page[1]), ' | ';
  }
  echo <<< EOF
<a href="logout.php">Logout</a></p>
</body>
</html>
EOF;
}

function dolink($page, $txt, $args = []) {
  $args['page'] = $page;
  $q = http_build_query($args, '', '&amp;');
  return "<a href=\"index.php?$q\">$txt</a>";
}

function print_table($table) {
  if (!$table)
    return;

  echo "<table><tr>\n";
  foreach ($table[0] as $key => $val) {
    echo "<th>$key</th>\n";
  }
  echo "</tr>\n";
  foreach ($table as $row) {
    echo "<tr>\n";
    foreach ($row as $val) {
      if (is_array($val))
        $val = implode('<br>', $val);
      echo "<td>$val</td>\n";
    }
    echo "</tr>\n";
  }
  echo "</table>\n";
}

<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

function html_header($title) {
  $user = $_SESSION['username'];
  $name = $_SESSION['name'];
  $role = $_SESSION['role'];

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
<p>User: $name ($user)<br>
Role: $role</p>
<p><a href="logout.php">Logout</a></p>
EOF;
}

function html_footer() {
  echo <<< EOF
</body>
</html>
EOF;
}

function dolink($page, $txt) {
  echo "<a href=\"index.php?page=$page\">$txt</a>";
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
        $val = implode(',', $val);
      echo "<td>$val</td>\n";
    }
    echo "</tr>\n";
  }
  echo "</table>\n";
}

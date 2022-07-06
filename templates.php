<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

function html_header($title) {
  global $username, $displayname, $role;
echo <<< EOF
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>PIC1: $title</title>
</head>
<body>
<p>User: $displayname ($username)<br>
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

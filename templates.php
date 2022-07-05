<?php

// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

function html_header($title) {
echo <<< EOF
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>$title</title>
</head>
<body>
EOF;
}

function html_footer() {
  echo <<< EOF
</body>
</html>
EOF;
}

<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'include.php';
require 'templates.php';
require 'db.php';
require 'auth.php';

setlocale(LC_ALL, 'C');
ini_set('default_charset', 'UTF-8');
ini_set('user_agent', USERAGENT);

$page = @$_REQUEST['page'];
$file = "pages/$page.php";

try {
  if (ctype_alpha($page) && file_exists($file)) {
    require $file;
  } else {
    require 'pages/main.php';
  }
} catch (PDOException $e) {
  if (IN_PRODUCTION) {
    echo "<p>Error while accessing the DB</p>";
  } else {
    print_r($e);
  }
}

html_footer();

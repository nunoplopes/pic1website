<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'include.php';
require 'templates.php';
require 'db.php';
require 'auth.php';
require 'validation.php';

$page = $_REQUEST['page'] ?? '';
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
    echo "<pre>", htmlspecialchars(print_r($e, true)), "</pre>";
  }
}

html_footer();

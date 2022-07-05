<?php

// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'config.php';
require 'templates.php';
require 'auth.php';

setlocale(LC_ALL, 'C');
ini_set('default_charset', 'UTF-8');
ini_set('user_agent', USERAGENT);

$page = "pages/" . @$_REQUEST['page'] . '.php';

if (ctype_alpha($page) && file_exists($page)) {
  require $page;
} else {
  require 'pages/main.php';
}

html_footer();

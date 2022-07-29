<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'config.php';
require 'vendor/autoload.php';

if (IN_PRODUCTION) {
  error_reporting(0);
} else {
  error_reporting(E_ALL);
  ini_set('display_errors', true);
}

setlocale(LC_ALL, 'C');
ini_set('default_charset', 'UTF-8');
ini_set('user_agent', USERAGENT);

ini_set('session.name', 'sessid');
ini_set('session.use_strict_mode', true);
ini_set('session.cookie_secure', IN_PRODUCTION);
ini_set('session.cookie_httponly', true);
ini_set('session.use_trans_sid', false);
$session_length = 90 * 24 * 3600; // 90 days
ini_set('session.cookie_lifetime', $session_length);
ini_set('session.gc_maxlifetime', $session_length);

// TODO: migrate this to an enum with PHP 8
define('ROLE_SUDO', 0);
define('ROLE_PROF', 1);
define('ROLE_TA', 2);
define('ROLE_STUDENT', 3);

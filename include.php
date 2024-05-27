<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'config.php';
require 'vendor/autoload.php';

error_reporting(E_ALL);
if (IN_PRODUCTION) {
  ini_set('display_errors', false);
  ini_set('log_errors', true);
  ini_set('log_errors_max_len', 4096);
  ini_set('error_log', 'error.log');
} else {
  ini_set('display_errors', true);
  ini_get('zend.assertions') == 1 or die('zend.assertions != 1');
}

setlocale(LC_ALL, 'C');
ini_set('default_charset', 'UTF-8');
ini_set('user_agent', USERAGENT);
date_default_timezone_set(TIMEZONE);

// TODO: migrate this to an enum with PHP 8
define('ROLE_SUDO', 0);
define('ROLE_PROF', 1);
define('ROLE_TA', 2);
define('ROLE_STUDENT', 3);

function is_higher_role($a, $b) {
  return $a < $b;
}

function is_deadline_current($deadline) {
  return new DateTimeImmutable() <= $deadline;
}

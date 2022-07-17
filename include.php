<?php
// Copyright (c) 2022-present Universidade de Lisboa.
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

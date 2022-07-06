<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'config.php';

if (IN_PRODUCTION) {
  error_reporting(0);
} else {
  error_reporting(E_ALL);
}

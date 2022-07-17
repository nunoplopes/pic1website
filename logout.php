<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'include.php';
require 'templates.php';
require 'auth.php';

session_destroy();
phpCAS::logout();

echo "<p>Logged out!</p>\n";

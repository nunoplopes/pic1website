<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require 'include.php';

session_start();
session_destroy();

echo "<p>Logged out!</p>\n";

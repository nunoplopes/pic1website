<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'include.php';
require 'db.php';
require 'fenix.php';
require 'github.php';

// Update student's group information
$year = get_current_year();
foreach (get_course_ids(get_term()) as $course) {
  foreach (get_groups($course) as $number => $students) {
    db_update_group($number, $year, implode(',', $students));
  }
}

// Check student's github activity

// TODO

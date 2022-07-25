<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'include.php';
require 'db.php';
require 'fenix.php';
require 'github.php';

// Update student's group information
$year = get_current_year();
//foreach (get_course_ids(get_term()) as $course) {
  foreach ([846035542880731] as $course) {
  foreach (get_groups($course) as $number => $data) {
    [$shift, $students] = $data;
    if (!$students)
      continue;

    $shift = db_fetch_shift($year, $shift);
    $group = db_fetch_group($year, $number, $shift);
    $group->resetStudents();
    foreach ($students as $id => $name) {
      $group->addStudent(db_fetch_or_add_user($id, $name, ROLE_STUDENT));
    }
  }
}

// Check student's github activity

// TODO


// Load licenses from SPDX
$url = 'https://raw.githubusercontent.com/spdx/license-list-data/master/json/licenses.json';
$data = json_decode(file_get_contents($url));
foreach ($data->licenses as $license) {
  db_update_license($license->licenseId, $license->name);
}

db_flush();

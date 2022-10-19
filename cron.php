<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'include.php';
require 'db.php';
require 'fenix.php';
require 'github.php';

for ($i = 1; $i < sizeof($argv); ++$i) {
  $arg = $argv[$i];
  if ($arg == '-h' || $arg == '--help') {
    echo <<< EOF
Usage: php crop.php <options>
--course-id <id>

EOF;
    exit;
  }
  if ($arg == '--course-id') {
    $courses = [$argv[++$i]];
  }
}

$year = get_current_year();
if (!isset($courses))
  $courses = get_course_ids(get_term());

// Update student's group information
foreach ($courses as $course) {
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


// Update list of Profs
// First remove permissions from all current profs
// We don't model roles per year (they are global)
// So we keep the current year's role only.
foreach (db_get_all_profs() as $user) {
  $user->role = ROLE_STUDENT;
}

foreach ($courses as $course) {
  foreach (get_course_teachers($course) as $prof) {
    $user = db_fetch_or_add_user($prof[0], $prof[1], $prof[2]);
    if (is_higher_role($prof[2], $user->role))
      $role = $prof[2];
  }
}


// Delete old sessions
foreach (db_get_all_sessions() as $session) {
  if (!$session->isFresh())
    db_delete_session($session);
}


// Check student's github activity

// TODO


// Load licenses from SPDX
$url = 'https://raw.githubusercontent.com/spdx/license-list-data/master/json/licenses.json';
$data = json_decode(file_get_contents($url));
foreach ($data->licenses as $license) {
  if (!$license->isDeprecatedLicenseId)
    db_update_license($license->licenseId, $license->name);
}

// List of programming languages
$languages = [
  'C',
  'C++',
  'C#',
  'Go',
  'Java',
  'JavaScript',
  'Perl',
  'PHP',
  'Python',
  'Ruby',
  'Rust',
  'Scala',
  'Swift',
  'TypeScript',
];

foreach ($languages as $l) {
  db_insert_prog_language($l);
}

db_flush();

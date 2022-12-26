<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'include.php';
require_once 'db.php';
require_once 'email.php';
require_once 'fenix.php';
require_once 'github.php';

$tasks = [
  'groups'      => "Update student's group information from fenix",
  'professors'  => 'Update list of professors/TAs from fenix',
  'gc_sessions' => 'Prune old sessions',
  'github'      => 'Update github information',
  'licenses'    => 'Update list of licenses',
  'prog_langs'  => 'Update list of programming languages',
];

for ($i = 1; $i < sizeof($argv); ++$i) {
  $arg = $argv[$i];
  if ($arg == '-h' || $arg == '--help') {
    echo <<< EOF
Usage: php crop.php <options>
--course-id <id>
--run <tasks>

Available tasks:

EOF;
    foreach ($tasks as $name => $desc) {
      echo " - $name:\t$desc\n";
    }
    exit;
  }
  if ($arg == '--course-id') {
    $courses = [$argv[++$i]];
  } elseif ($arg == '--run') {
    $run_tasks = explode(',', $argv[++$i]);
  }
}

$year = get_current_year();

function get_courses() {
  global $courses;
  if (!isset($courses))
    $courses = get_course_ids(get_term());
  return $courses;
}


// Update student's group information
function run_groups() {
  global $year;
  foreach (get_courses() as $course) {
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
}


// Update list of Profs
function run_professors() {
  // First remove permissions from all current profs
  // We don't model roles per year (they are global)
  // So we keep the current year's role only.
  foreach (db_get_all_profs(true) as $user) {
    if ($user->role != ROLE_SUDO)
      $user->role = ROLE_STUDENT;
  }

  foreach (get_courses() as $course) {
    foreach (get_course_teachers($course) as $prof) {
      $user = db_fetch_or_add_user($prof[0], $prof[1], $prof[2]);
      if (is_higher_role($prof[2], $user->role))
        $user->role = $prof[2];
    }
  }
}


// Delete old sessions
function run_gc_sessions() {
  foreach (db_get_all_sessions() as $session) {
    if (!$session->isFresh())
      db_delete_session($session);
  }
}


// Check student's github activity
function run_github() {
  global $year;
  $groups = db_fetch_groups($year);
  foreach ($groups as $group) {
    foreach (db_get_all_patches($group) as $patch) {
      try {
        if ($patch->isStillOpen())
          $patch->updateStats();
        echo "Done patch: $patch->id\n";
      } catch (ValidationException $ex) {
        email_ta($group, "Patch $patch->id is broken", <<< EOF
Cron job failed to process patch $patch->id
Group: $group->id
Error: $ex
EOF);
      }
    }
  }
}


// Load licenses from SPDX
function run_licenses() {
  $url = 'https://raw.githubusercontent.com/spdx/license-list-data/master/json/licenses.json';
  $data = json_decode(file_get_contents($url));
  foreach ($data->licenses as $license) {
    if (!$license->isDeprecatedLicenseId)
      db_update_license($license->licenseId, $license->name);
  }
}

function run_prog_langs() {
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
}


if (!isset($run_tasks))
  $run_tasks = array_keys($tasks);

foreach ($run_tasks as $task) {
  echo "Running $task...\n";
  if (empty($tasks[$task]))
    die("No such task: $task\n");
  try {
    "run_$task"();
  } catch (Throwable $ex) {
    email_profs("PIC1: Cron job failed",
                "Cron had an exception when running $task:\n$ex");
  }
}

db_flush();

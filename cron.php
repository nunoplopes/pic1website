<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'include.php';
require_once 'db.php';
require_once 'email.php';
require_once 'fenix.php';
require_once 'github.php';
require_once 'templates.php';

$start = time();

$tasks = [
  'prune_cache' => 'Prune cache',
  'groups'      => "Update student's group information from fenix",
  'professors'  => 'Update list of professors/TAs from fenix',
  'gc_sessions' => 'Prune old sessions',
  'patch_stats' => 'Update patch statistics',
  'repository'  => 'Update repository information',
  'licenses'    => 'Update list of licenses',
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

$checkpoint_idx = 0;
if (empty($checkpoint_start)) $checkpoint_start = 0;
if (empty($max_exec_time)) $max_exec_time = 3600;

class CheckPointException extends Exception {
  public int $idx;
  public function __construct($idx) {
    $this->idx = $idx;
  }
}

function checkpoint() {
  global $start, $max_exec_time, $checkpoint_idx, $checkpoint_start;

  $elapsed = time() - $start;
  if ($elapsed > $max_exec_time) {
    db_flush();
    throw new CheckPointException($checkpoint_idx);
  }

  return ++$checkpoint_idx <= $checkpoint_start;
}

function error_profs($subject, $msg) {
  if (IN_PRODUCTION)
    email_profs($subject, $msg);
  echo "ERROR: $subject\n$msg\n";
}

function error_ta($group, $subject, $msg) {
  if (IN_PRODUCTION)
    email_ta($group, $subject, $msg);
  echo "ERROR: $subject (group $group)\n$msg\n";
}

function error_group($group, $subject, $msg) {
  if (IN_PRODUCTION)
    email_group($group, $subject, $msg);
  echo "ERROR: $subject (group $group)\n$msg\n";
}

function get_courses() {
  global $courses;
  if (!isset($courses))
    $courses = get_course_ids(get_term());
  return $courses;
}


function run_prune_cache() {
  db_delete_cache();
  `rm -fr .cache`;
}

// Update student's group information
function run_groups() {
  global $year;
  $base_number = 0;
  foreach (get_courses() as $course) {
    $base_number += 1000;
    foreach (get_groups($course) as $number => $data) {
      [$shift, $students] = $data;
      $number += $base_number;

      $shift = db_fetch_shift($year, $shift);
      $group = db_fetch_group($year, $number);

      if (!$students) {
        if ($group) {
          if ($group->patches->isEmpty()) {
            db_delete($group);
          } else {
            $group->resetStudents();
          }
        }
        continue;
      }

      if (!$group)
        $group = db_create_group($year, $number, $shift);
      $group->shift = $shift;
      $group->resetStudents();
      foreach ($students as $id => $name) {
        $group->addStudent(
          db_fetch_or_add_user($id, $name, ROLE_STUDENT, '', '', false, false));
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
      // fenix returns full prof names, not display names
      $user = db_fetch_or_add_user($prof[0], $prof[1], $prof[2], '', '', false,
                                   /*update_data=*/false);
      if (is_higher_role($prof[2], $user->role))
        $user->role = $prof[2];
    }
  }
}


// Delete old sessions
function run_gc_sessions() {
  foreach (db_get_all_sessions() as $session) {
    if (!$session->isFresh())
      db_delete($session);
  }
}

// Update patch statistics
function run_patch_stats() {
  global $year;

  foreach (db_fetch_groups($year) as $group) {
    if (checkpoint())
      continue;

    foreach (db_get_all_patches($group) as $patch) {
      if (!$patch->isStillOpen())
        continue;
      try {
        $oldstatus = $patch->getStatus();
        $oldhash   = $patch->hash;
        $patch->updateStats();
        $newstatus = $patch->getStatus();
        $newhash   = $patch->hash;

        if ($newstatus != $oldstatus) {
          $patch->comments->add(
            new PatchComment($patch,
              "Status changed: $oldstatus → $newstatus"));
          email_ta($group,
                   "PIC1: Patch $patch->id status changed (group $group)",
                   "Patch $patch->id of group $group changed status from " .
                   "$oldstatus to $newstatus\n" .
                   link_patch($patch));
          echo "Patch $patch->id changed status from $oldstatus to ",
               $newstatus, "\n";
        }

        if ($newhash != $oldhash && $newhash) {
          $patch->comments->add(
            new PatchComment($patch, "New branch hash: $newhash"));
          echo "Patch $patch->id changed hash from $oldhash to $newhash\n";
        }

        if (!$patch->getPR() && $patch->findAndSetPR()) {
          handle_new_pr($patch, $group, $patch->getPR());
        }

        if ($pr = $patch->getPR()) {
          foreach ($patch->getHashes() as $hash) {
            $failed = $pr->failedCIjobs($hash);
            foreach ($failed as $job) {
              $patch->addCIError($hash, $job['name'], $job['url'],
                                 $job['time']);
            }
          }
        }

        echo "Updated patch $patch->id\n";

      } catch (ValidationException $ex) {
        error_ta($group, "Patch $patch->id is broken", <<< EOF
Cron job failed to process patch $patch->id
Group: $group->id
Error: $ex
EOF);
      }
    }
  }
}

// Check student's repository activity (new PRs)
function run_repository() {
  global $year;

  foreach (db_fetch_groups($year) as $group) {
    if (checkpoint())
      continue;

    $group_repo = $group->getRepository();
    if (!$group_repo || !$group_repo->isValid()) {
      if ($group_repo) {
        email_group($group,
                    "PIC1: repository no longer exists",
                    "Repository no longer exists: " . $group_repo . "\n\n".
                    "Please change it here: " . link_group($group));
      }
      continue;
    }

    echo "Processing group $group\n";

    foreach ($group->students as $user) {
      if (!$user->getRepoUser())
        continue;

      foreach ($user->getRepoUser()->getUnprocessedEvents() as $event) {
        if (!$event instanceof PROpenedEvent)
          continue;

        $pr = $event->pr;
        if ($pr->repository != $group_repo)
          continue;

        echo "Processing new PR $pr\n";

        $processed = false;
        foreach ($group->patches as $patch) {
          if ($patch->origin() != $pr->origin())
            continue;

          $patch->setPR($pr);
          handle_new_pr($patch, $group, $pr);
          $processed = true;
          break;
        }

        if (!$processed) {
          $patch = Patch::factory($group, $pr->branchURL(), PatchType::BugFix,
            "This patch entry was automatically generated.\n".
            "The PR was opened without permission!",
            $user, '', /*ignore_errors=*/true);
          $patch->setPR($pr);
          $patch->status = PatchStatus::PROpenIllegal;
          $group->patches->add($patch);
          db_save($patch);

          error_group($group,
                      "PIC1: PR opened without a corresponding patch entry",
                      "PR $pr of group $group was opened ".
                      "without a corresponding patch entry on the website.\n\n".
                      "Created a patch entry automatically: ".
                      link_patch($patch));
        }
      }
    }
  }
}


// Load licenses from SPDX
function run_licenses() {
  $url = 'https://raw.githubusercontent.com/spdx/license-list-data/master/json/licenses.json';
  $data = json_decode(file_get_contents($url));
  foreach ($data->licenses as $license) {
    db_update_license($license->licenseId, $license->name);
  }
}


if (!isset($run_tasks))
  $run_tasks = array_keys($tasks);

foreach ($run_tasks as $task) {
  echo "Running $task...\n";
  if (empty($tasks[$task]))
    die("No such task: $task\n");
  try {
    $task = "run_$task";
    $task();
  } catch (CheckPointException $ex) {
    throw $ex;
  } catch (Throwable $ex) {
    if (isset($throw_exceptions)) {
      throw $ex;
    }
    error_profs("PIC1: Cron job failed",
                "Cron had an exception when running $task:\n$ex");
    return;
  }
}

db_flush();


function handle_new_pr($patch, $group, $pr) {
  switch ($patch->status) {
  case PatchStatus::Merged:
  case PatchStatus::MergedIllegal:
    break;
  case PatchStatus::Approved:
  case PatchStatus::PROpen:
  case PatchStatus::NotMerged:
    $patch->status = PatchStatus::PROpen;
    break;
  default:
    $patch->status = PatchStatus::PROpenIllegal;
    $patch->comments->add(
      new PatchComment($patch, "PR opened without approval"));
    error_group($group,
                "PIC1: PR opened without approval",
                "PR $pr of group $group was opened ".
                "without prior approval.\n\n" . link_patch($patch));
    break;
  }
}

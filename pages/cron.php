<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

$argv = [];
$max_exec_time = 15;
$checkpoint_start = (int)@$_GET['checkpoint'];

if ($checkpoint_start)
  $info_message = "Continuing with offset $checkpoint_start...";

if (isset($_GET['task'])) {
  $run_tasks = [$_GET['task']];
} else {
  $run_tasks = [];
}

ob_start();
try {
  $throw_exceptions = true;
  require 'cron.php';
  if ($run_tasks) {
    $success_message = 'All done!';
  }
} catch (CheckPointException $e) {
  $refresh_url
    = dourl('cron', ['task' => $run_tasks[0], 'checkpoint' => $e->idx]);
  $info_message = "Will continue with offset $e->idx...";
} finally {
  $monospace = ob_get_contents();
  ob_end_clean();
}

foreach ($tasks as $task => $desc) {
  $lists['Run'][] = dolink('cron', $desc, ['task' => $task]);
}

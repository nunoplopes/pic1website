<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

auth_require_at_least(ROLE_PROF);

html_header('Run cron jobs');

if (isset($_GET['task'])) {
  $run_tasks = [$_GET['task']];
  echo "<p>Log:</p><pre>";
} else {
  $run_tasks = [];
}
$argv = [];
require 'cron.php';

if ($run_tasks)
  echo "</pre>\n";

echo "<p>Run: </p><ul>";
foreach ($tasks as $task => $desc) {
  echo '<li><a href="index.php?page=cron&amp;task=', $task,
       "\">$desc</a></li>\n";
}
echo "</ul>";

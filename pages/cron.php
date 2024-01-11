<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

auth_require_at_least(ROLE_PROF);

html_header('Run cron jobs');

$argv = [];
$max_exec_time = 9;
$checkpoint_start = (int)@$_GET['checkpoint'];

if ($checkpoint_start)
  echo "<p>Continuing with offset $checkpoint_start</p>\n";

if (isset($_GET['task'])) {
  $run_tasks = [$_GET['task']];
  echo "<p>Log:</p><pre>";
} else {
  $run_tasks = [];
}

try {
  require 'cron.php';
} catch (CheckPointException $e) {
  $url = dourl('cron', ['task' => $run_tasks[0], 'checkpoint' => $e->idx], '&');
  echo <<<HTML
</pre>
<script>
  setTimeout(function() {
    window.location.replace("$url");
  }, 3000);
</script>
<p>Will continue with offset $e->idx...</p>
</body></html>
HTML;
  exit();
}

if ($run_tasks)
  echo "\nAll done!</pre><p>&nbsp;</p>\n";

echo "<p>Run: </p><ul>";
foreach ($tasks as $task => $desc) {
  echo '<li>', dolink('cron', $desc, ['task' => $task]), "</li>\n";
}
echo "</ul>";

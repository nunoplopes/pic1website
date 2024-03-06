<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

auth_require_at_least(ROLE_PROF);

html_header('Delete Patch');

if (empty($_GET['id']))
  die('Missing id');

$patch = db_fetch_patch_id($_GET['id']);
if (!$patch)
  die('Non-existing patch');

if (!empty($_GET['sure'])) {
  db_delete($patch);
  echo "<p>Patch deleted</p>";
} else {
  $name = $patch->submitter->shortName();
  $link = dolink('rmpatch', "Yes, I'm sure",
                 ['id' => (int)$_GET['id'], 'sure' => 1]);
  echo <<<HTML
<p>Are you sure you want to delete patch $patch->id of $name?</p>
<p>$link</p>
HTML;
}

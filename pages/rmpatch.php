<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

if (empty($_GET['id']))
  die('Missing id');

$patch = db_fetch_patch_id($_GET['id']);
if (!$patch)
  die('Non-existing patch');

if (!empty($_GET['sure'])) {
  db_delete($patch);
  $success_message = "Patch deleted";
} else {
  $name = $patch->getSubmitter()->shortName();
  $confirm["Are you sure you want to delete patch $patch->id of $name?"]
    = dolink('rmpatch', "Yes, I'm sure", ['id' => $patch->id, 'sure' => 1]);
}

<?php

// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'fenix.php';

session_start();

if (isset($_GET['key']) &&
    password_verify('4X6EM' . $_GET['key'] . 'fgOGi', SUDO_HASH)) {
  $_SESSION['username'] = 'ist00000';
  $user = new User;
  $user->id   = 'ist00000';
  $user->name = 'Sudo';
  $user->role = 0;
}

if (isset($_GET['fenixlogin'])) {
  if (isset($_GET['code'])) {
    $data = fenix_get_auth_token($_GET['code']);
    if (!$data)
      die("Failed to authenticate Fenix's token");

    $_SESSION['fenix_data'] = $data;
    $person = fenix_get_personal_data($data);

    $user = db_fetch_or_add_user($person['username'], $person['name']);
    $_SESSION['username'] = $person['username'];

  } else if ($_GET['error']) {
    die("Fenix returned an error: " .
        htmlspecialchars($_GET['error_description']));
  } else {
    die("Couldn't understand Fenix's reply\n");
  }
}

if (empty($user)) {
  if (isset($_SESSION['username'])) {
    $user = db_fetch_user($_SESSION['username']);
  } else {
    header('Location: ' . fenix_get_auth_url());
    exit;
  }
}

function has_group_permissions($group) {
  switch ($_SESSION['role']) {
    case 'superuser': return true;
    case 'TA':
      return false; // TODO
    case 'student':
      return in_array($_SESSION['username'], $group['students']);
  }
}

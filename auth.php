<?php

// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'fenix.php';

session_start();

define('ROLE_SUDO', 0);
define('ROLE_PROF', 1);
define('ROLE_TA', 2);
define('ROLE_STUDENT', 3);

if (isset($_GET['key']) &&
    password_verify('4X6EM' . $_GET['key'] . 'fgOGi', SUDO_HASH)) {
  $_SESSION['username'] = 'ist00000';
  $user = db_fetch_or_add_user('ist00000', 'Sudo', ROLE_SUDO);
}

if (isset($_GET['fenixlogin'])) {
  if (isset($_GET['code'])) {
    // prevent reauthentication through browser refresh
    if (empty($_SESSION['fenix_code']) ||
        $_SESSION['fenix_code'] !== $_GET['code']) {
      $data = fenix_get_auth_token($_GET['code']);
      if (!$data)
        die("Failed to authenticate Fenix's token");

      $_SESSION['fenix_code'] = $_GET['code'];
      $_SESSION['fenix_data'] = $data;
      $person = fenix_get_personal_data($data);

      $user = db_fetch_or_add_user($person['username'], $person['name'],
                                   ROLE_STUDENT);
      $_SESSION['username'] = $user->id;

      // Let's cleanup the URL and remove those fenix codes
      $cleanurl = 'https://' . $_SERVER['HTTP_HOST'] .
                  '/index.php?page=' . $_GET['page'];
      header("Location: $cleanurl");
      exit;
    }
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

function auth_at_least($role) {
  global $user;
  return $user->role <= $role;
}

function has_group_permissions($group) {
  global $user;
  switch ($user->role) {
    case ROLE_SUDO:
    case ROLE_PROF:
      return true;
    case ROLE_TA:
      return false; // TODO
    case ROLE_STUDENT:
      return in_array($group->id, $user->groups);
  }
}

function get_role_string() {
  global $user;
  switch ($user->role) {
    case ROLE_SUDO:    return 'Sudo';
    case ROLE_PROF:    return 'Professor';
    case ROLE_TA:      return 'TA';
    case ROLE_STUDENT: return 'student';
  }
}

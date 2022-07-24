<?php

// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'fenix.php';

session_start();

$auth_user__ = null;
function get_user() : User {
  return $GLOBALS['auth_user__'];
}

function auth_set_user($user) {
  $GLOBALS['auth_user__'] = $user;
  $_SESSION['username'] = $user->id;
}


if (isset($_GET['key']) &&
    password_verify('4X6EM' . $_GET['key'] . 'fgOGi', SUDO_HASH)) {
  $_SESSION['username'] = 'ist00000';
  $auth_user__ = db_fetch_or_add_user('ist00000', 'Sudo', ROLE_SUDO);
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

      $auth_user__ = db_fetch_or_add_user($person['username'], $person['name'],
                                          ROLE_STUDENT);
      $_SESSION['username'] = get_user()->id;

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

if ($auth_user__ === null) {
  if (isset($_SESSION['username'])) {
    $auth_user__ = db_fetch_user($_SESSION['username']);
  } else {
    header('Location: ' . fenix_get_auth_url());
    exit;
  }
}

function validate_role($role) {
  return is_int($role) && $role >= ROLE_SUDO && $role <= ROLE_STUDENT;
}

function auth_at_least($role) {
  return get_user()->role <= $role;
}

function auth_require_at_least($role) {
  if (!auth_at_least($role))
    die('Unauthorized access');
}

function has_group_permissions($group) {
  $user = get_user();
  switch ($user->role) {
    case ROLE_SUDO:
    case ROLE_PROF:
      return true;
    case ROLE_TA:
      return false; // TODO
    case ROLE_STUDENT:
      return $user->groups->contains($group);
  }
}

function get_all_roles($include_sudo) {
  $roles = [
    ROLE_PROF    => 'Professor',
    ROLE_TA      => 'TA',
    ROLE_STUDENT => 'Student'
  ];
  if ($include_sudo)
    $roles[ROLE_SUDO] = 'Sudo';
  return $roles;
}

function get_role_string() {
  return get_all_roles(true)[get_user()->role];
}

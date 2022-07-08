<?php

// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

session_start();

if (isset($_GET['key']) &&
    password_verify('4X6EM' . $_GET['key'] . 'fgOGi', SUDO_HASH)) {
  $_SESSION['role'] = 'superuser';
  $_SESSION['username'] = 'ist00000';
  $_SESSION['name'] = 'Sudo';
}

if (empty($_SESSION['role'])) {
  // TODO: contact fenix
  $_SESSION['role'] = 'Prof';
  $_SESSION['username'] = 'ist11111';
  $_SESSION['name'] = 'Maria Manuel';
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

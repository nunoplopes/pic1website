<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

$db = new PDO(PDO_DSN, PDO_USER, PDO_PWD);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function db_update_group($number, $year, $students) {
  global $db;
  $db->prepare('REPLACE INTO groups(id, year, students) VALUES (?,?,?)')
     ->execute([$number, $year, $students]);
}

function db_get_group_years() {
  global $db;
  return $db->query('SELECT DISTINCT year from groups')
            ->fetchAll(PDO::FETCH_COLUMN, 0);
}

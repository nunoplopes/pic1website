<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

$db = new PDO(PDO_DSN, PDO_USER, PDO_PWD);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt_cache = [];

function db_prepare($stmt) {
  global $db, $stmt_cache;
  if (empty($stmt_cache[$stmt]))
    $stmt_cache[$stmt] = $db->prepare($stmt);
  return $stmt_cache[$stmt];
}

function db_insert_student($id, $name) {
  db_prepare('INSERT OR IGNORE INTO students(id, name) VALUES (?,?)')
    ->execute([$id, $name]);
}

function db_update_group($id, $year, $students) {
  $students = implode(',', $students);

  // doesn't update row if it exists already
  db_prepare('INSERT OR IGNORE INTO groups(id, year, students) VALUES (?,?,?)')
    ->execute([$id, $year, $students]);
  db_prepare('UPDATE groups SET students=? WHERE id=? AND year=?')
    ->execute([$students, $id, $year]);
}

function db_get_group_years() {
  global $db;
  return $db->query('SELECT DISTINCT year FROM groups ORDER BY year DESC')
            ->fetchAll(PDO::FETCH_COLUMN, 0);
}

function db_fetch_groups($year) {
  $stmt = db_prepare('SELECT * FROM groups WHERE YEAR=?');
  $stmt->execute([$year]);
  $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($data as &$row) {
    $row['students'] = explode(',', $row['students']);
  }
  return $data;
}

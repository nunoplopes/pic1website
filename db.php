<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'include.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\EntityManager;

$config = ORMSetup::createAnnotationMetadataConfiguration(
  [__DIR__ . '/entities'], /*isDevMode:*/ !IN_PRODUCTION, /*proxyDir:*/ '.proxies');
$config->setAutoGenerateProxyClasses(!IN_PRODUCTION);

if (!IN_PRODUCTION) {
  class SQLLoogger implements Doctrine\DBAL\Logging\SQLLogger {
    public function startQuery($sql, ?array $params = null,
                               ?array $types = null) {
      echo "\n\n<!-- $sql -->\n\n";
    }

    public function stopQuery() {}
  }
  $config->setSQLLogger(new SQLLoogger);
}

$connection = DriverManager::getConnection(['url' => DB_DSN], $config);
$entityManager = new EntityManager($connection, $config);

function db_flush() {
  global $entityManager;
  $entityManager->flush();
}

function db_fetch_entity($name, $orderby) {
  global $entityManager;
  return $entityManager->getRepository($name)->findBy([], [$orderby => 'ASC']);
}

function db_fetch_user($username) : ?User {
  global $entityManager;
  return $entityManager->find('User', $username);
}

function db_fetch_or_add_user($username, $name, $role, $email = '',
                              $photo = '', $dummy = false,
                              $update_data = true) : User {
  $user = db_fetch_user($username);
  if ($user) {
    if (!$update_data)
      return $user;

    $changed = false;
    if ($user->name != $name) {
      $user->name = $name;
      $changed = true;
    }
    if ($email && $user->email != $email) {
      $user->email = $email;
      $changed = true;
    }
    if ($photo && $user->photo != $photo) {
      $user->photo = $photo;
      $changed = true;
    }
    if ($changed)
      db_flush();
    return $user;
  }

  global $entityManager;
  $user = new User($username, $name, $email, $photo, $role, $dummy);
  $entityManager->persist($user);
  $entityManager->flush();
  return $user;
}

function db_get_all_users() {
  return db_fetch_entity('User', 'id');
}

function db_get_all_profs($include_tas = false) {
  global $entityManager;
  $roles = [ROLE_SUDO, ROLE_PROF];
  if ($include_tas)
    $roles[] = ROLE_TA;
  $users = $entityManager->getRepository('User')->findByRole($roles,
                                                             ['name' => 'ASC']);
  return array_filter($users, function($u) { return !$u->isDummy(); });
}

function db_save($obj) {
  global $entityManager;
  $entityManager->persist($obj);
  db_flush();
}

function db_delete($obj) {
  if ($obj) {
    global $entityManager;
    $entityManager->remove($obj);
    db_flush();
  }
}

function db_fetch_session($id) {
  global $entityManager;
  return $entityManager->find('Session', $id);
}

function db_get_all_sessions() {
  global $entityManager;
  return $entityManager->getRepository('Session')->findAll();
}

function db_get_group_years() {
  global $entityManager;
  return $entityManager->createQueryBuilder()
                       ->from('ProjGroup', 'g')
                       ->select('g.year')->distinct()
                       ->orderBy('g.year', 'DESC')
                       ->getQuery()
                       ->getArrayResult();
}

function db_fetch_groups($year) {
  global $entityManager;
  return $entityManager->getRepository('ProjGroup')
                       ->findByYear($year, ['group_number' => 'ASC']);
}

function db_fetch_group($year, $number, Shift $shift) : ?ProjGroup {
  global $entityManager;
  return $entityManager->getRepository('ProjGroup')
                       ->findOneBy(['year'=>$year, 'group_number'=> $number]);
}

function db_create_group($year, $number, Shift $shift) : ProjGroup {
  global $entityManager;
  $group = new ProjGroup($number, $year, $shift);
  $entityManager->persist($group);
  return $group;
}

function db_fetch_shift($year, $name) : Shift {
  global $entityManager;
  $shift = $entityManager->getRepository('Shift')
                         ->findOneBy(['year' => $year, 'name' => $name]);
  if ($shift)
    return $shift;
  $shift = new Shift($name, $year);
  $entityManager->persist($shift);
  return $shift;
}

function db_fetch_shift_id($id) : ?Shift {
  global $entityManager;
  return $entityManager->find('Shift', $id);
}

function db_fetch_shifts($year) {
  global $entityManager;
  return $entityManager->getRepository('Shift')
                       ->findByYear($year, ['name' => 'ASC']);
}

function db_fetch_group_id($id) : ?ProjGroup {
  global $entityManager;
  return $entityManager->find('ProjGroup', $id);
}

function db_fetch_license($id) : ?License {
  global $entityManager;
  return $entityManager->find('License', $id);
}

function db_update_license($id, $name) {
  global $entityManager;
  $license = $entityManager->find('License', $id);
  if ($license)
    $license->name = $name;
  else
    $entityManager->persist(new License($id, $name));
}

function db_fetch_prog_language($id) : ?ProgLanguage {
  global $entityManager;
  return $entityManager->find('ProgLanguage', $id);
}

function db_insert_prog_language($name) {
  global $entityManager;
  if (!db_fetch_prog_language($name))
    $entityManager->persist(new ProgLanguage($name));
}

function db_fetch_deadline($year) : Deadline {
  global $entityManager;
  $deadline = $entityManager->find('Deadline', $year);
  if (!$deadline) {
    $deadline = new Deadline($year);
    $entityManager->persist($deadline);
    db_flush();
  }
  return $deadline;
}

function db_get_all_patches($group) {
  global $entityManager;
  return $entityManager->getRepository('Patch')
                       ->findByGroup($group);
}

function db_fetch_patch_id($id) : ?Patch {
  global $entityManager;
  return $entityManager->find('Patch', $id);
}

function db_get_merged_patch_stats() {
  global $entityManager;
  return $entityManager->createQueryBuilder()
                       ->from('Patch', 'p')
                       ->where('p.status = ' . PATCH_MERGED . ' OR '.
                               'p.status = ' . PATCH_MERGED_ILLEGAL)
                       ->select(['g.year',
                                 'COUNT(p.id) AS patches',
                                 'SUM(p.lines_added) AS lines_added',
                                 'SUM(p.lines_deleted) AS lines_deleted',
                                 'SUM(p.files_modified) AS files_modified'])
                       ->join('p.group', 'g')
                       ->groupBy('g.year')
                       ->orderBy('g.year')
                       ->getQuery()
                       ->getArrayResult();
}

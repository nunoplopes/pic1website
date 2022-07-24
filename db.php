<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'include.php';

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

$config = Setup::createAnnotationMetadataConfiguration([__DIR__ . '/entities'],
                                                       !IN_PRODUCTION);

$entityManager = EntityManager::create(['url' => DB_DSN], $config);

function db_flush() {
  global $entityManager;
  $entityManager->flush();
}

function db_fetch_user($username) : ?User {
  global $entityManager;
  return $entityManager->find('User', $username);
}

function db_fetch_or_add_user($username, $name, $role) : User {
  $user = db_fetch_user($username);
  if ($user)
    return $user;

  global $entityManager;
  $user = new User;
  $user->id   = $username;
  $user->name = $name;
  $user->role = $role;
  $entityManager->persist($user);
  $entityManager->flush();
  return $user;
}

function db_get_all_users() {
  global $entityManager;
  return $entityManager->getRepository('User')->findBy([], ['id' => 'ASC']);
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

function db_fetch_group($year, $number) : ProjGroup {
  global $entityManager;
  $group = $entityManager->getRepository('ProjGroup')
                         ->findBy(['year' => $year, 'group_number' => $number]);
  if ($group)
    return $group[0];

  $group               = new ProjGroup;
  $group->group_number = $number;
  $group->year         = $year;
  return $group;
}

function db_fetch_group_id($id) : ?ProjGroup {
  global $entityManager;
  return $entityManager->getRepository('ProjGroup')->find($id);
}

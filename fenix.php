<?php
// Copyright (c) 2022-present Universidade de Lisboa.
// Distributed under the MIT license that can be found in the LICENSE file.

// API doc: https://fenixedu.org/dev/api/

require_once 'include.php';

function get_fnx($path, $year = null) {
  $url = "https://fenix.tecnico.ulisboa.pt/api/fenix/v1/$path";
  if ($year)
    $url .= "?academicTerm=$year";
  return json_decode(@file_get_contents($url));
}

function get_current_year() {
  $year = (int)date('Y');
  return date('n') >= MONTH_NEW_YEAR ? $year : ($year-1);
}

// get a string like 2004/2005
function get_term() {
  $year = get_current_year();
  return "$year/" . ($year+1);
}

function get_course_ids($year) {
  $ids = [];
  foreach (get_fnx("degrees", $year) as $degree) {
    if (array_search($degree->acronym, FENIX_DEGREES) !== false) {
      foreach (get_fnx("degrees/".$degree->id."/courses") as $course) {
        if (array_search($course->acronym, FENIX_COURSES)  !== false) {
          $ids[] = $course->id;
        }
      }
    }
  }
  return array_unique($ids);
}

function get_groups($course) {
  $groups = [];
  $data = get_fnx("courses/$course/groups");
  if (!$data)
    return [];
  foreach ($data[0]->associatedGroups as $group) {
    foreach ($group->members as $m) {
      $groups[$group->groupNumber][$m->username] = $m->name;
    }
  }
  return $groups;
}

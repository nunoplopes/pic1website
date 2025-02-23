<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

$year = filter_by(['year']);

$final_grade = db_get_final_grade($year);
if (!$final_grade) {
  terminate("No final grade formula defined for year $year");
}
$final_grade = $final_grade->formula;

preg_match_all('/[A-Z]+\d*/', $final_grade, $vars);
$vars = $vars[0];

$lang = new ExpressionLanguage();
$data = db_get_final_grade($year);
$final_grade = $lang->parse($final_grade, $vars);

$grades = [];

foreach (db_get_all_grades($year) as $grade) {
  for ($i = 1; $i <= 4; ++$i) {
    $grades[$grade->user][$grade->milestone][$i]
      = compute_grade($grade->milestone, $grade, $i);
  }
  $grades_milestones[$grade->user][$grade->milestone]
    = array_sum($grades[$grade->user][$grade->milestone]);
}

foreach (db_fetch_users_year($year) as $user) {
  $data = [
    'id'   => $user->id,
    'name' => $user->shortName(),
    '_large_table' => true,
  ];
  foreach ($vars as $milestone) {
    $data[$milestone] = $grades_milestones[$user->id][$milestone] ?? 0;
  }
  $data['final'] = round($lang->evaluate($final_grade, $data), 0);
  $table[] = $data;
}

function compute_grade($milestone, $grade, $num) {
  $field  = "field$num";
  $points = "points$num";
  $range  = "range$num";
  return
    round($grade->$field * $milestone->$points / ($milestone->$range * 10), 2);
}

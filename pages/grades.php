<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\Node\NameNode;

if (auth_at_least(ROLE_TA)) {
  $groups = filter_by(['group', 'year', 'shift', 'own_shifts']);
} else {
  $group = get_user()->getGroup();
  if ($group !== null) {
    $groups = [$group];
  } else {
    $groups = [];
  }
}

if ($groups) {
  $year = $groups[0]->year;
} else {
  $year = get_current_year();
}

$milestones = db_get_all_milestones($year);

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

$display_formula['title'] = 'Final Grade';
$display_formula['items'] = gen_formula_data($final_grade->getNodes());

$grades = [];

foreach (db_get_all_grades($year) as $grade) {
  for ($i = 1; $i <= 4; ++$i) {
    if ($grade->milestone->{"field$i"}) {
      $grades[$grade->user->id][$grade->milestone->name][$i]
        = compute_grade($grade, $i);
    }
  }
  $grades_milestones[$grade->user->id][$grade->milestone->name]
    = array_sum($grades[$grade->user->id][$grade->milestone->name]);
}

foreach ($groups as $group) {
  foreach ($group->students as $user) {
    $data = [
      'id'   => $user->id,
      'name' => $user->shortName(),
      '_large_table' => true,
    ];
    $values = [];
    foreach ($vars as $milestone) {
      $ms = get_milestone($milestone);
      $num = $grades_milestones[$user->id][$milestone] ?? 0;
      $values[$milestone] = $num;
      $data[$milestone]['text']    = number_format($num, 2);
      $data[$milestone]['tooltip'] = $ms ? $ms->description : '';

      foreach ($grades[$user->id][$milestone] ?? [] as $i => $grade) {
        if ($descr = $ms->{"field$i"}) {
          $data[$milestone]['tooltip'] .= "\n$descr: ".number_format($grade, 2);
        }
      }
    }
    $data['Final'] = (int)round($lang->evaluate($final_grade, $values), 0);
    $table[] = $data;
  }
}

if (auth_at_least(ROLE_TA) && sizeof($groups) == 1) {
  mk_eval_box($year, null, null, $groups[0]);
}

if (auth_at_least(ROLE_PROF) && $table !== null) {
  $hist = array_fill(0, 21, 0);
  $hist_groups = [];
  foreach ($table as $row) {
    $grade = $row['Final'];
    ++$hist[$grade];

    $loc = (int)(db_fetch_user($row['id'])->getGroup()->group_number / 1000);
    if (!isset($hist_groups[$loc])) {
      $hist_groups[$loc] = array_fill(0, 21, 0);
    }
    ++$hist_groups[$loc][$grade];
  }

  $plots['Overall'] = $hist;
  foreach ($hist_groups as $loc => $hist) {
    $plots["Group $loc"] = $hist;
  }
}

function compute_grade($grade, $num) {
  $milestone = $grade->milestone;
  $field  = "field$num";
  $points = "points$num";
  $range  = "range$num";
  return
    round($grade->$field * $milestone->$points / ($milestone->$range * 10), 2);
}

function get_milestone($name) {
  global $milestones;
  foreach ($milestones as $milestone) {
    if ($milestone->name === $name) {
      return $milestone;
    }
  }
  return null;
}

function gen_formula_data(Node $node, $precedence = 0) {
  $op_precedence = [
    '+' => 1,
    '-' => 1,
    '*' => 2,
    '/' => 2,
  ];

  $subscripts = ["\u{2080}", "\u{2081}", "\u{2082}", "\u{2083}", "\u{2084}",
                 "\u{2085}", "\u{2086}", "\u{2087}", "\u{2088}", "\u{2089}"];

  $ops = [
    '*' => "\u{D7}",
  ];

  $items = [];

  if ($node instanceof NameNode) {
    $name = $node->attributes['name'];
    $milestone = get_milestone($name);

    $name = preg_replace_callback('/([A-Z]+)(\d+)/',
      function ($m) use ($subscripts) {
        return $m[1] . $subscripts[$m[2]];
      }, $name);

    if ($milestone) {
      $title = $milestone->description;
      for ($i = 1; $i <= 4; ++$i) {
        if ($milestone->{"field$i"}) {
          $points = number_format($milestone->{"points$i"} / 10, 2);
          $title .= "\n{$milestone->{"field$i"}}: $points";
        }
      }
    } else {
      $title = '';
    }

    $items[] = [
      'var'   => $name,
      'color' => 'indigo',
      'title' => $title
    ];
  } elseif ($node instanceof BinaryNode) {
    $operator = $node->attributes['operator'];
    $new_precedence = $op_precedence[$operator];

    // Add open parentheses if necessary
    if ($new_precedence < $precedence) {
      $items[] = [
        'var'   => '(',
        'color' => 'black'
      ];
    }

    $op = [[
      'var'   => $ops[$operator] ?? $operator,
      'color' => 'black'
    ]];

    $items
      = array_merge($items,
                    gen_formula_data($node->nodes['left'], $new_precedence),
                    $op,
                    gen_formula_data($node->nodes['right'], $new_precedence));

    // Add closing parentheses if necessary
    if ($new_precedence < $precedence) {
      $items[] = [
        'var'   => ')',
        'color' => 'black'
      ];
    }
  } else {
    assert(false, 'Unknown node type');
  }
  return $items;
}

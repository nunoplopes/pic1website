<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

function do_ext_link($page, $args = []) {
  return 'https://' . $_SERVER['HTTP_HOST'] . '/' . dourl($page, $args);
}

function link_patch(Patch $patch) {
 return do_ext_link('editpatch', ['id' => $patch->id]);
}

function link_group(ProjGroup $group) {
  return do_ext_link('listproject',  ['id' => $group->id]);
 }

function dourl($page, $args = []) {
  $args['page'] = $page;
  $q = http_build_query($args, '', '&');
  return "index.php?$q";
}

function dolink_ext($url, $txt) {
  return ['label' => $txt, 'url' => $url];
}

function dolink_group(ProjGroup $group, $txt) {
  return dolink('listproject',  $txt, ['id' => $group->id]);
}

function dolink($page, $txt, $args = []) {
  return dolink_ext(dourl($page, $args), $txt);
}

function format_big_number($n) {
  if ($n < 1000)
    return $n;
  if ($n < 1000000)
    return round($n / 1000) . "\u{202F}k";
  return round($n / 1000000, 1) . "\u{202F}M";
}

function handle_form(&$obj, $hide_fields, $readonly, $only_fields = null,
                     $in_required = null) {
  global $form, $formFactory, $request, $success_message;
  $form = $formFactory->createBuilder(FormType::class);

  $class = new ReflectionClass($obj);

  $not_all_readonly = false;

  // get_object_vars doesn't trigger lazy loading; force it here
  if (method_exists($obj, '__isInitialized') && !$obj->__isInitialized()) {
    $obj->__load();
  }

  foreach (get_object_vars($obj) as $name => $orig_value) {
    if (in_array($name, $hide_fields) ||
        ($only_fields && !in_array($name, $only_fields)))
      continue;

    $property = $class->getProperty($name);
    $attributes = $property->getAttributes(Doctrine\ORM\Mapping\Column::class);
    $column = null;
    if (!empty($attributes)) {
      $column = $attributes[0]->newInstance();
    }

    $print_name = strtr($name, '_', ' ');

    $disabled = false;
    if (in_array($name, $readonly))
      $disabled = true;
    else
      $not_all_readonly = true;

    $required = true;
    if ($in_required)
      $required = in_array($name, $in_required);

    if (is_bool($orig_value)) {
      $form->add($name, CheckboxType::class, [
        'label'    => $print_name,
        'data'     => $orig_value,
        'required' => false,
        'disabled' => $disabled,
      ]);
    }
    elseif ($orig_value instanceof DateTimeInterface) {
      $form->add($name, DateTimeType::class, [
        'label'    => $print_name,
        'data'     => $orig_value,
        'input'    => 'datetime_immutable',
        'widget'   => 'single_text',
        'disabled' => $disabled,
        'required' => $required,
        'attr'     => ['style' => 'width: 220px'],
      ]);
    }
    elseif (isset($annotations[1]->targetEntity)) {
      $orderby = $annotations[1]->targetEntity::orderBy();
      $entities = db_fetch_entity($annotations[1]->targetEntity, $orderby);

      $vals = [];
      foreach ($entities as $entity) {
        $vals[(string)$entity] = $entity->id;
      }
      $form->add($name, ChoiceType::class, [
        'label'    => $print_name,
        'choices'  => $vals,
        'data'     => $orig_value,
        'disabled' => $disabled,
        'required' => $required,
      ]);
    }
    elseif ($orig_value instanceof UnitEnum) {
      $form->add($name, EnumType::class, [
        'class'        => get_class($orig_value),
        'choice_label' => fn ($v) => $v->label(),
        'label'        => $print_name,
        'data'         => $orig_value,
        'disabled'     => $disabled,
        'required'     => $required,
      ]);
    }
    else {
      $extra_attrs = [];
      $getter = "getstr_$name";
      if (method_exists($obj, $getter)) {
        $val = $obj->$getter();
      } else {
        $val = (string)$orig_value;
      }
      if (str_starts_with($val, 'https://') || str_contains($name, 'url')) {
        $field_class = UrlType::class;
      } else {
        if ($class->getProperty($name)->getType()->getName() === 'int') {
          $field_class = IntegerType::class;
          $extra_attrs['empty_data'] = 0;
        } else if ($column && $column->length > 200) {
          $field_class = TextareaType::class;
          $extra_attrs['attr'] = ['rows' => 5];
         } else {
          $field_class = TextType::class;
        }
      }
      $form->add($name, $field_class, [
        'label'    => $print_name,
        'data'     => $val,
        'disabled' => $disabled,
        'required' => $required,
      ] + $extra_attrs);
    }
  }

  $form->add('submit', SubmitType::class, [
    'label'    => 'Save changes',
    'disabled' => !$not_all_readonly,
  ]);

  $form = $form->getForm();
  $form->handleRequest($request);

  if ($form->isSubmitted() && $form->isValid()) {
    $errors = [];
    foreach ($obj as $name => $val) {
      if (in_array($name, $hide_fields) ||
          in_array($name, $readonly) ||
          ($only_fields && !in_array($name, $only_fields)))
        continue;

      $set = "set_$name";
      $newval = $form->get($name)->getData() ?? '';
      try {
        if (method_exists($obj, $set)) {
          $obj->$set($newval);
        } else {
          $obj->$name = $newval;
        }
      } catch (ValidationException $ex) {
        $errors[$name] = $ex;
      }
    }

    if ($errors) {
      $str = 'Failed to validate all fields:';
      foreach ($errors as $name => $error) {
        $print_name = strtr($name, '_', ' ');
        $str .= "\n$print_name: " . $error->getMessage();
      }
      terminate($str);
    }
    $success_message = 'Database updated!';
  }
}

function filter_by($filters, $extra_filters = []) {
  global $page, $request, $formFactory, $select_form;
  $select_form = $formFactory->createNamedBuilder('', FormType::class);

  $select_form->add('page', HiddenType::class, [
    'data' => $page,
  ]);

  $all_years = db_get_group_years();

  $selected_year   = $request->query->get('year', $all_years[0]['year']);
  $selected_shift  = $request->query->get('shift', null);
  $all_shifts      = $request->query->get('all_shifts', false) ? true : false;
  $selected_group  = $request->query->get('group', 'all');
  $selected_repo   = $request->query->get('repo', 'all');
  $own_shifts_only = !$all_shifts;
  $return = null;

  if ($selected_group !== 'all' && !$request->query->has('year')) {
    $group = db_fetch_group_id($selected_group);
    if (!$group) {
      die('Invalid group');
    }
    $selected_year = $group->year;
  }

  $selected_shift_obj = $selected_shift && $selected_shift != 'all'
                          ? db_fetch_shift_id($selected_shift) : null;

  if (in_array('year', $filters)) {
    $years = [];
    foreach ($all_years as $year) {
      $years[$year['year']] = $year['year'];
    }
    $select_form->add('year', ChoiceType::class, [
      'label'   => 'Year',
      'choices' => $years,
      'data'    => $selected_year,
    ]);
    $return = $selected_year;
  }
  if (in_array('shift', $filters)) {
    $shifts = ['All' => 'all'];
    foreach (db_fetch_shifts($selected_year) as $shift) {
      if (!has_shift_permissions($shift))
        continue;
      if ($own_shifts_only && $shift->prof != get_user())
        continue;
      $shifts[$shift->name] = $shift->id;
    }
    $select_form->add('shift', ChoiceType::class, [
      'label'   => 'Shift',
      'choices' => $shifts,
      'data'    => $selected_shift,
    ]);
  }
  if (in_array('group', $filters)) {
    $groups = ['All' => 'all'];
    $return = [];
    foreach (db_fetch_groups($selected_year) as $group) {
      if (!has_group_permissions($group))
        continue;
      if ($own_shifts_only && $group->prof() != get_user())
        continue;
      if ($selected_shift_obj && $group->shift != $selected_shift_obj)
        continue;
      if ($selected_repo != 'all' && $group->getRepositoryId() != $selected_repo)
        continue;

      if ($selected_group == 'all' || $group->id == $selected_group)
        $return[] = $group;
      $groups[$group->group_number] = $group->id;
    }
    $select_form->add('group', ChoiceType::class, [
      'label'   => 'Group',
      'choices' => $groups,
      'data'    => $selected_group,
    ]);
  }
  if (in_array('repo', $filters)) {
    $repos = [];
    foreach (db_fetch_groups($selected_year) as $group) {
      if (!has_group_permissions($group))
        continue;

      if ($repo = $group->getRepositoryId())
        $repos[$repo] = true;
    }
    $repos = array_keys($repos);
    natsort($repos);

    $repos = ['All' => 'all'] + array_combine($repos, $repos);
    $select_form->add('repo', ChoiceType::class, [
      'label'   => 'Repository',
      'choices' => $repos,
      'data'    => $selected_repo,
    ]);
  }
  if (in_array('own_shifts', $filters)) {
    $select_form->add('all_shifts', CheckboxType::class, [
      'label'    => 'Show all shifts',
      'data'     => $all_shifts,
      'required' => false,
    ]);
  }

  if ($extra_filters) {
    $return = [$return];
    foreach ($extra_filters as $var => $label) {
      $value = $request->query->get($var, false) ? true : false;
      $select_form->add($var, CheckboxType::class, [
        'label'    => $label,
        'data'     => $value,
        'required' => false,
      ]);
      $return[] = $value;
    }
  }

  $select_form = $select_form->getForm();
  $select_form->handleRequest($request);
  return $return;
}

function mk_eval_box(int $year, ?string $page, ?User $student,
                     ?ProjGroup $group) {
  if (!auth_at_least(ROLE_TA))
    return;
  if ($group && !has_group_permissions($group))
    return;
  if ($student && $student->getGroup() &&
      !has_group_permissions($student->getGroup()))
    return;

  if ($group) {
    $students = $group->students;
  } else {
    $students = [$student];
  }
  $id = 0;

  foreach (db_get_milestone($year, $page) as $milestone) {
    if (!$milestone->individual && sizeof($students) > 1) {
      $form = mk_eval_form($milestone, $students[0], 'Group grade', $id++);
      if ($form->isSubmitted() && $form->isValid()) {
        foreach ($students as $student) {
          set_grade($milestone, $student, $form);
        }
      }
    }

    foreach ($students as $student) {
      $form = mk_eval_form($milestone, $student, $student->shortName(), $id++);
      if ($form->isSubmitted() && $form->isValid()) {
        set_grade($milestone, $student, $form);
      }
    }
  }
}

function mk_eval_form($milestone, $student, $name, $id) {
  global $formFactory, $request, $eval_forms;

  $grade = db_get_grade($milestone, $student);
  $form = $formFactory->createNamedBuilder('eval'.$id++, FormType::class);

  $form->add('name', TextType::class, [
    'data'     => $name,
    'disabled' => true,
  ]);

  for ($i = 1; $i <= 4; ++$i) {
    if ($milestone->{"field$i"}) {
      $form->add('field'.$i, RangeType::class, [
        'label'    => $milestone->{"field$i"},
        'data'     => $grade ? $grade->{"field$i"} : 0,
        'attr'     => [
          'min' => 0,
          'max' => $milestone->{"range$i"},
        ],
      ]);
    }
  }

  $form->add('late', IntegerType::class, [
    'label' => 'Late Days',
    'data'  => $grade ? $grade->late_days : 0,
  ]);
  $form->add('save', SubmitType::class);
  $form = $form->getForm();
  $eval_forms[] = ['title' => $milestone->description, 'fields' => $form];
  $form->handleRequest($request);

  return $form;
}

function set_grade($milestone, $student, $form) {
  global $success_message;

  $grade = db_get_grade($milestone, $student);
  if (!$grade) {
    $grade = new Grade();
    $grade->milestone = $milestone;
    $grade->user = $student;
  }
  for ($i = 1; $i <= 4; ++$i) {
    if ($milestone->{"field$i"})
      $grade->{"field$i"} = (int)$form->get('field'.$i)->getData();
  }
  $grade->late_days = (int)$form->get('late')->getData();
  db_save($grade);
  $success_message = 'Database updated!';
}

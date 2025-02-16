<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
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

function handle_form(&$obj, $hide_fields, $readonly, $only_fields = null,
                     $extra_buttons = null, $in_required = null) {
  global $form, $formFactory, $request, $success_message;
  $form = $formFactory->createBuilder(FormType::class);

  $class = new ReflectionClass($obj);
  $docReader = new AnnotationReader();

  $not_all_readonly = false;

  // get_object_vars doesn't trigger lazy loading; force it here
  if (method_exists($obj, '__isInitialized') && !$obj->__isInitialized()) {
    $obj->__load();
  }

  foreach (get_object_vars($obj) as $name => $orig_value) {
    if (in_array($name, $hide_fields) ||
        ($only_fields && !in_array($name, $only_fields)))
      continue;

    $annotations
      = $docReader->getPropertyAnnotations($class->getProperty($name));

    $column = null;
    foreach ($annotations as $t) {
      if (get_class($t) === 'Doctrine\ORM\Mapping\Column') {
        $column = $t;
        break;
      }
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
    elseif (method_exists($obj, "get_$name"."_options")) {
      $vals = [];
      $method_name = "get_$name"."_options";
      foreach ($obj->$method_name() as $id => $str) {
        $vals[$str] = $id;
      }
      $form->add($name, ChoiceType::class, [
        'label'    => $print_name,
        'choices'  => $vals,
        'data'     => $orig_value,
        'disabled' => $disabled,
        'required' => $required,
      ]);
    }
    else {
      $getter = "getstr_$name";
      if (method_exists($obj, $getter)) {
        $val = $obj->$getter();
      } else {
        $val = (string)$orig_value;
      }
      if (str_starts_with($val, 'https://')) {
        $field_class = UrlType::class;
      } else {
        $length      = $column ? $column->length : 0;
        $field_class = $length > 200 ? TextareaType::class : TextType::class;
      }
      $form->add($name, $field_class, [
        'label'    => $print_name,
        'data'     => $val,
        'disabled' => $disabled,
        'required' => $required,
      ]);
    }
  }

  $form->add('submit', SubmitType::class, [
    'label'    => 'Save changes',
    'disabled' => !$not_all_readonly,
  ]);

  if ($extra_buttons) {
    foreach ($extra_buttons as $name => $args) {
      $key = $args[0];
      $value = $args[1];
      echo "<input type=\"submit\" value=\"$name\"",
           " onclick=\"this.form.$key.value='$value'\">\n";
    }
  }

  $form = $form->getForm();
  $form->handleRequest($request);

  if ($form->isSubmitted() && $form->isValid()) {
    $errors = [];
    foreach (get_object_vars($obj) as $name => $val) {
      if (in_array($name, $hide_fields) ||
          in_array($name, $readonly) ||
          ($only_fields && !in_array($name, $only_fields)))
        continue;

      $set = "set_$name";
      $newval = $form->get($name)->getData() ?? '';
      try {
        $obj->$set($newval);
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

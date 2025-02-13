<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Doctrine\Common\Annotations\AnnotationReader;

function quote($str) {
  return "'" . htmlspecialchars($str) . "'";
}

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

function dolink($page, $txt, $args = []) {
  return ['label' => $txt, 'url' => dourl($page, $args)];
}

function format_text($text) {
  return nl2br(htmlspecialchars(wordwrap($text, 80, "\n", true)));
}

function handle_form(&$obj, $hide_fields, $readonly, $only_fields = null,
                     $extra_buttons = null, $flush_db = true) {
  $class = new ReflectionClass($obj);
  $docReader = new AnnotationReader();

  if (!empty($_POST['submit'])) {
    $errors = [];
    foreach (get_object_vars($obj) as $name => $val) {
      if (in_array($name, $hide_fields) ||
          in_array($name, $readonly) ||
          ($only_fields && !in_array($name, $only_fields)))
        continue;

      $set = "set_$name";

      if (is_bool($val)) {
        $obj->$set(isset($_POST[$name]));
        continue;
      }

      if (!isset($_POST[$name]))
        continue;

      $set = "set_$name";
      try {
        $obj->$set(trim($_POST[$name]));
      } catch (ValidationException $ex) {
        $errors[$name] = $ex;
      }
    }

    if ($errors) {
      echo "<span style=\"color: red\">\n",
           "<p>Failed to validate all fields:</p><ul>\n";
      foreach ($errors as $name => $error) {
        $print_name = strtr($name, '_', ' ');
        echo "<li>$print_name: ", $error->getMessage(), "</li>\n";
      }
      echo "</ul></span><p>&nbsp;</p>\n";
    } else {
      if ($flush_db)
        db_flush();
      echo '<p style="color: green">Database updated!</p>';
    }
  }

  echo '<form action="',htmlspecialchars($_SERVER['REQUEST_URI']),
                    '" method="post">';
  echo '<input type="hidden" name="submit" value="1">';
  echo "<table>\n";

  $not_all_readonly = false;

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
    $length = $column ? $column->length : 0;

    $print_name = strtr($name, '_', ' ');

    $getter = "getstr_$name";

    if ($orig_value instanceof DateTimeInterface) {
      $val = $orig_value->format('Y-m-d\TH:i:s');
    } elseif (is_object($orig_value) &&
              $orig_value instanceof \Doctrine\ORM\PersistentCollection) {
      $val = array_map(function($e) { return htmlspecialchars($e); },
                       $orig_value->toArray());
      $val = implode(', ', $val);
    } elseif (method_exists($obj, $getter)) {
      $val = htmlspecialchars($obj->$getter());
    } else {
      $val = htmlspecialchars((string)$orig_value);
    }

    if (str_starts_with($val, 'https://'))
      $print_name = "<a href=\"$val\">$print_name</a>";

    $freeze = '';
    if (in_array($name, $readonly))
      $freeze = ' readonly';
    else
      $not_all_readonly = true;

    echo "<tr><td><label for=\"$name\">$print_name:</label></td><td>\n";
    if (is_bool($orig_value)) {
      $checked = '';
      if ($val)
        $checked = ' checked';
      echo "<input type=\"checkbox\" id=\"$name\" name=\"$name\" ",
           "value=\"true\"$checked>";
    }
    else if ($orig_value instanceof DateTimeInterface) {
      echo "<input type=\"datetime-local\" id=\"$name\" name=\"$name\"",
           " step=1 value=\"$val\">";
    }
    else if (isset($annotations[1]->targetEntity)) {
      $orderby = $annotations[1]->targetEntity::orderBy();
      echo "<select name=\"$name\" id=\"$name\">\n";

      $entities = db_fetch_entity($annotations[1]->targetEntity, $orderby);
      foreach ($entities as $entity) {
        $selected = '';
        if ($entity == $orig_value)
          $selected = ' selected';
        echo "<option value=\"", htmlspecialchars($entity->id), "\"$selected>",
             htmlspecialchars((string)$entity), "</option>\n";
      }
      echo "</select>";
    }
    else if (method_exists($obj, "get_$name"."_options")) {
      if (in_array($name, $readonly))
        $freeze = ' disabled';
      echo "<select name=\"$name\" id=\"$name\"$freeze>\n";

      $method_name = "get_$name"."_options";
      foreach ($obj->$method_name() as $id => $name) {
        $selected = '';
        if ($id == $orig_value)
          $selected = ' selected';
        echo "<option value=\"$id\"$selected>", htmlspecialchars($name),
             "</option>\n";
      }
      echo "</select>";
    }
    else {
      if ($length > 200) {
        echo "<textarea id=\"$name\" name=\"$name\" rows=\"5\" cols=\"60\"",
             "$freeze>$val</textarea>";
      } else {
        echo "<input type=\"text\" id=\"$name\" name=\"$name\" ",
             "value=\"$val\" size=\"60\"$freeze>";
      }
    }
    echo "</td></tr>\n";
  }
  echo "</table><p>";

  if ($not_all_readonly)
    echo "<input type=\"submit\" value=\"Save changes\">";
  echo "</p>\n";

  if ($extra_buttons) {
    echo "<p>";
    foreach ($extra_buttons as $name => $args) {
      $key = $args[0];
      $value = $args[1];
      echo "<input type=\"submit\" value=\"$name\"",
           " onclick=\"this.form.$key.value='$value'\">\n";
    }
    echo "</p>\n";
  }
  echo "</form>\n";
}

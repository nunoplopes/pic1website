<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

class ValidationException extends Exception {
  public function __construct($message) {
    parent::__construct($message);
  }
}

function check_url($url) {
  if ($url !== '' &&
      (!preg_match('@^https?://@', $url) ||
       !filter_var($url, FILTER_VALIDATE_URL) ||
       // reject https://https:// as it's a common mistake
       preg_match('@^https?://https?://@', $url)))
    throw new ValidationException('Malformed URL');
  return $url;
}

function check_email($email) {
  return filter_var($email, FILTER_VALIDATE_EMAIL) &&
         str_ends_with($email, '@tecnico.ulisboa.pt');
}

function transliterate_str($str) {
  $rules = <<<EOF
:: NFD (NFC);
:: [:Nonspacing Mark:] Remove;
:: Lower();
:: NFC (NFD);
EOF;
  return Transliterator::createFromRules($rules)->transliterate($str);
}

function has_similar_name($base, $name) {
  $base = explode(' ', transliterate_str($base));
  $name = explode(' ', transliterate_str($name));
  return !array_diff($name, $base);
}

function check_reasonable_name($name, $group) {
  if (!preg_match('/^\p{L}[\p{L}\']*(?: \p{L}[\p{L}\']*){1,7}$/Su', $name))
    throw new ValidationException("Invalid name: $name");

  if (preg_match('/\p{Lu}{2}/Su', $name))
    throw new ValidationException(
      "Name has too many capitalized letters: $name");

  if (preg_match("/'\p{L}*'/Su", $name))
    throw new ValidationException("Name has too many ': $name");

  $matched = false;
  foreach ($group->students as $user) {
    $matched |= has_similar_name($user->name, $name);
  }
  if (!$matched)
    throw new ValidationException(
      "Name doesn't match any of the group's student names: $name");
}

function check_wrapped_commit_text($text, $width) {
  if (preg_match('/^(?!Co-authored-by: ).{'.($width+1).'}/Sum', $text))
    throw new ValidationException(
      "Text is not wrapped to $width characters:\n$text");
}

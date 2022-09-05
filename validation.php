<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

class ValidationException extends Exception {
  public function __construct($message) {
    parent::__construct($message);
  }
}

function check_url($url) {
  if ($url !== '' && !preg_match('@^https?://@', $url))
    throw new ValidationException('Malformed URL');
  return $url;
}

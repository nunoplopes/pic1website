<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

require_once 'github.php';

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

function check_repo_url($url) {
  $url = check_url($url);
  if (GitHub\parse_repo_url($url))
    return $url;
  throw new ValidationException('Unsupported repository URL');
}

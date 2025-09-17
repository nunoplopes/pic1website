<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

interface RepositoryUserInterface
{
  static function parse(string $user) : ?string;
  static function isValid(RepositoryUser $user) : bool;
  static function profileURL(RepositoryUser $user) : string;
  static function name(RepositoryUser $user) : ?string;
  static function email(RepositoryUser $user) : ?string;
  static function company(RepositoryUser $user) : ?string;
  static function location(RepositoryUser $user) : ?string;
  static function getUnprocessedEvents(RepositoryUser $user) : array;
}

<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Http\Client\Common\Plugin\Cache\Generator\HeaderCacheKeyGenerator;

$github_builder = new Github\HttpClient\Builder;
$github_client  = new \Github\Client($github_builder);
$github_client->authenticate(GH_TOKEN, null, \Github\AuthMethod::ACCESS_TOKEN);

$github_client->addCache(
  new Symfony\Component\Cache\Adapter\PdoAdapter(DB_DSN, 'cache', 3*3600),
  ['cache_key_generator' => new HeaderCacheKeyGenerator(['If-None-Match']),
   'default_ttl' => 3*3600,
   'respect_response_cache_directives' => []]);

function github_set_etag($etag) {
  $GLOBALS['github_builder']->addHeaderValue('If-None-Match', $etag);
}

function github_remove_etag() {
  $GLOBALS['github_builder']->clearHeaders();
}

function github_parse_date($date) {
  // FIXME: 'p' is PHP 8 only
  //if ($ret = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sp', $date))
  if ($ret = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $date,
                                                  new \DateTimeZone('Z')))
    return $ret;
  throw new Exception("Couldn't parse github date: $date");
}

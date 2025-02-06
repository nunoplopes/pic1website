<?php
// Copyright (c) 2022-present Instituto Superior Técnico.
// Distributed under the MIT license that can be found in the LICENSE file.

use Http\Client\Common\HttpMethodsClient;
use Http\Client\Common\HttpMethodsClientInterface;
use Http\Client\Common\Plugin;
use Http\Client\Common\PluginClientFactory;
use Http\Client\Common\Plugin\HeaderSetPlugin;
use Http\Client\Common\Plugin\HistoryPlugin;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Cache\Adapter\PdoAdapter;

class MyJournal implements \Http\Client\Common\Plugin\Journal {
  public function addSuccess(RequestInterface $request,
                             ResponseInterface $response) {
    print_r($request);
    print_r($response);
  }

  public function addFailure(RequestInterface $request, \Throwable $exception) {
    print_r($request);
    print_r($exception);
  }
}


// hack around the limitation of the addCache API that it strictly follows
// the max-cache directive, which is not what we want
class MyHttpBuilder extends Github\HttpClient\Builder {
  private $timeout;
  private $client = null;
  private $plugins = [];

  public function __construct($timeout) {
    $this->timeout = $timeout;
  }

  public function addPlugin(Plugin $plugin): void {
    $this->plugins[] = $plugin;
    $this->client = null;
  }

  public function removePlugin(string $fqcn):void {
    foreach ($this->plugins as $idx => $plugin) {
      if ($plugin instanceof $fqcn) {
        unset($this->plugins[$idx]);
        $this->client = null;
      }
    }
  }

  public function addHeaderValue(string $header, string $headerValue): void {
    $this->plugins[] = new HeaderSetPlugin([$header => $headerValue]);
    $this->client = null;
  }

  public function clearHeaders(): void {
    $this->removePlugin(Plugin\HeaderAppendPlugin::class);
  }

  public function getHttpClient(): HttpMethodsClientInterface {
    if (!$this->client) {
      $stream = Psr17FactoryDiscovery::findStreamFactory();
      $pool = new PdoAdapter(DB_DSN, 'cache', $this->timeout);
      $config = ['respect_response_cache_directives' => [],
                 'default_ttl' => $this->timeout];
      $plugins = $this->plugins;
      $plugins[] = Plugin\CachePlugin::serverCache($pool, $stream, $config);
      $plugins[] = new HeaderSetPlugin(['User-Agent' => USERAGENT]);
      //$plugins[] = new HistoryPlugin(new MyJournal);
      $this->client = new HttpMethodsClient(
          (new PluginClientFactory())->createClient(
            Psr18ClientDiscovery::find(), $plugins),
          Psr17FactoryDiscovery::findRequestFactory(),
          $stream
      );
    }
    return $this->client;
  }
}

$github_builder = new MyHttpBuilder(5 * 60);
$github_client  = new \Github\Client($github_builder);
$github_client->authenticate(GH_TOKEN, null, \Github\AuthMethod::ACCESS_TOKEN);

$github_client_cached = new \Github\Client(new MyHttpBuilder(10*24*3600));
$github_client_cached
  ->authenticate(GH_TOKEN, null, \Github\AuthMethod::ACCESS_TOKEN);


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

<?php

/**
 * @file
 * Windows-safe native generation: avoids Drush's shell-based batch subprocess. */

use Drupal\aculta_portal\Environment\DeployerEnvironment;
use Drupal\simple_sitemap\Queue\QueueWorker;

$host = \Drupal::request()->getHost();
if (!in_array($host, ['localhost', '127.0.0.1'], TRUE) && !str_ends_with($host, 'aculta.toca.net.br')) {
  throw new RuntimeException('Local or test host only.');
}
$generator = \Drupal::service('simple_sitemap.generator');
$generator->rebuildQueue()->generate(QueueWorker::GENERATE_TYPE_BACKEND);
$xml = simplexml_load_string($generator->getContent());
if (!$xml) {
  throw new RuntimeException('Invalid sitemap XML.');
}
$xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
$urls = array_map('strval', $xml->xpath('//s:loc'));
// Check generated URLs against the hosts allowed for the active environment.
$deployFile = dirname(__DIR__)
  . '/web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json';
$deploy = json_decode(
  (string) file_get_contents($deployFile),
  TRUE,
  512,
  JSON_THROW_ON_ERROR,
);
$profile = DeployerEnvironment::current(dirname(__DIR__), 'test');
$allowedHosts = array_map(
  static fn(string $url): ?string => parse_url($url, PHP_URL_HOST) ?: NULL,
  $deploy['robots_policy'][$profile]['hosts'] ?? [],
);
$allowedHosts = array_values(array_filter($allowedHosts));
if ($allowedHosts === []) {
  throw new RuntimeException("Hosts do perfil $profile ausentes em deploy.json.");
}
foreach ($urls as $url) {
  $parts = parse_url($url);
  if (($parts['scheme'] ?? NULL) !== 'https'
    || !in_array($parts['host'] ?? NULL, $allowedHosts, TRUE)
    || str_contains($parts['path'] ?? '', '/node/12')) {
    throw new RuntimeException('Unexpected sitemap URL.');
  }
}
$suiteOut = getenv('SUITE_OUT');
if (!is_string($suiteOut) || $suiteOut === '' || !is_dir($suiteOut) || !is_writable($suiteOut)) {
  throw new RuntimeException('SUITE_OUT must point to a writable directory outside the repository.');
}
$repository = realpath(dirname(__DIR__));
$artifactDirectory = realpath($suiteOut);
if ($repository === FALSE || $artifactDirectory === FALSE
  || $artifactDirectory === $repository
  || str_starts_with($artifactDirectory, $repository . DIRECTORY_SEPARATOR)) {
  throw new RuntimeException('SUITE_OUT must resolve outside the repository.');
}
file_put_contents($artifactDirectory . '/final-sitemap-urls.json', json_encode($urls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo count($urls) . " official HTTPS sitemap URLs generated using the native backend API: OK\n";

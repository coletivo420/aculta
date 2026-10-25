<?php
/** Windows-safe native generation: avoids Drush's shell-based batch subprocess. */
$host = \Drupal::request()->getHost();
if (!in_array($host, ['localhost', '127.0.0.1'], TRUE) && !str_ends_with($host, 'aculta.toca.net.br')) throw new RuntimeException('Local or test host only.');
$generator = \Drupal::service('simple_sitemap.generator');
$generator->rebuildQueue()->generate(\Drupal\simple_sitemap\Queue\QueueWorker::GENERATE_TYPE_BACKEND);
$xml = simplexml_load_string($generator->getContent());
if (!$xml) throw new RuntimeException('Invalid sitemap XML.');
$xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
$urls = array_map('strval', $xml->xpath('//s:loc'));
// A base vem do perfil do ambiente declarado em deploy.json (sitemap.<perfil>.index_base), conforme o host atual.
$deploy = json_decode((string) file_get_contents(dirname(__DIR__) . '/web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$profile = str_ends_with($host, 'aculta.toca.net.br') ? 'test' : 'production';
$base = $deploy['sitemap'][$profile]['index_base'] ?? throw new RuntimeException("Sitemap base do perfil $profile ausente em deploy.json.");
foreach ($urls as $url) if (!str_starts_with($url, $base . '/') || str_contains($url, '/node/12')) throw new RuntimeException('Unexpected sitemap URL.');
file_put_contents(dirname(__DIR__) . '/tmp/final-sitemap-urls.json', json_encode($urls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo count($urls) . " official HTTPS sitemap URLs generated using the native backend API: OK\n";

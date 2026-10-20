<?php
/** Assertions and public-content inventory, excluding private users and form submissions. */
$host = \Drupal::request()->getHost();
if (!in_array($host, ['localhost', '127.0.0.1'], TRUE) && !str_ends_with($host, 'aculta.toca.net.br')) throw new RuntimeException('Local or test host only.');
$assert = static function(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); echo "$message: OK\n"; };
$assert(\Drupal::config('system.site')->get('name') === 'Associação Cultural Antiproibicionista', 'Public identity');
$assert(!isset(\Drupal::config('core.extension')->get('theme')['olivero']), 'Olivero uninstalled');
$active = \Drupal::service('config.storage'); $sync = new \Drupal\Core\Config\FileStorage(dirname(__DIR__) . '/config/sync');
$deploy = json_decode((string) file_get_contents(dirname(__DIR__) . '/web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'), TRUE);
// Arquivos dependentes de ambiente são comparados pelo deployer (environment_bound), não aqui.
$environment_bound = array_map(static fn(array $e): string => basename($e['file'], '.yml'), $deploy['environment_bound'] ?? []);
// Linha de base DT-O10 (scripts/institution/CONFIG-DRIFT-BASELINE.json). Só pode diminuir.
$baseline_file = json_decode((string) file_get_contents(dirname(__DIR__) . '/scripts/institution/CONFIG-DRIFT-BASELINE.json'), TRUE);
$known_drift = $baseline_file['names'];
$differences = [];
foreach (array_unique(array_merge($sync->listAll(), $active->listAll())) as $name) {
  if (in_array($name, $environment_bound, TRUE)) continue;
  if (json_encode($active->read($name)) !== json_encode($sync->read($name))) $differences[] = $name;
}
$new_drift = array_values(array_diff($differences, $known_drift));
$assert(!$new_drift, 'Nenhuma diferença nova entre a configuração ativa e config/sync (DT-O10). Novas: ' . implode(', ', $new_drift));
$test_site_mail = NULL;
foreach ($deploy['profiles']['test']['rules'] ?? [] as $rule) {
  if (($rule['file'] ?? '') === 'config/sync/system.site.yml' && preg_match('/^mail: (.+)$/', (string) ($rule['with'] ?? ''), $m)) $test_site_mail = trim($m[1]);
}
$assert($test_site_mail !== NULL && \Drupal::config('system.site')->get('mail') === $test_site_mail, 'Institutional site mail matches the test profile of config/deploy.json');
$assert(($sync->read('system.mail')['interface']['default'] ?? NULL) === 'aculta_mailer', 'Versioned default mailer is camada de envio E-Mails ACULTA (aculta_mailer).');
$assert(!$sync->exists('smtp.settings'), 'Legacy smtp.settings is not versioned (drupal/smtp removed).');
$manifest = ['note' => 'Content is NOT included in config export. Preserve IDs/UUIDs during the separately planned secure migration; this is an inventory, not a database dump.', 'entities' => []];
foreach (['node', 'block_content', 'menu_link_content', 'path_alias', 'redirect', 'media', 'file'] as $type) {
  foreach (\Drupal::entityTypeManager()->getStorage($type)->loadMultiple() as $entity) $manifest['entities'][$type][] = ['id' => $entity->id(), 'uuid' => $entity->uuid(), 'bundle' => $entity->bundle(), 'label' => $entity->label(), 'published' => $entity instanceof \Drupal\Core\Entity\EntityPublishedInterface ? $entity->isPublished() : NULL];
}
foreach (['node_type','block_content_type','menu'] as $type) foreach (\Drupal::entityTypeManager()->getStorage($type)->loadMultiple() as $entity) {
  $assert((bool) $entity->getDescription(), 'Administrative description ' . $type . ':' . $entity->id());
}
$templates = glob(DRUPAL_ROOT . '/themes/custom/aculta420/templates/*.html.twig');
$twig = \Drupal::service('twig');
foreach ($templates as $path) {
  $name = str_replace('\\', '/', substr($path, strlen(DRUPAL_ROOT) + 1));
  $twig->compileSource(new \Twig\Source(file_get_contents($path), $name, $path));
}
echo count($templates) . " custom Twig templates compile: OK\n";
$assets = \Drupal::entityTypeManager()->getStorage('file')->loadMultiple();
// Linha de base: imagens do catálogo da loja e logos já conhecidos (DT-O10, decisão de catálogo pendente).
// Arquivo novo sem registro falha o gate.
$known_files = ['bloco-sativa420-square-512w.webp', 'baque-sativa-red-square-512.webp', 'podplant420-stacked-on-light-512w.webp', 'camiseta-bloco-sativa-2026.jpg', 'camiseta-bloco-sativa-medidas.jpg', 'camiseta-bloco-sativa-2.jpg', 'camiseta-bloco-sativa2.jpg', 'camiseta-bloco-sativa-3.jpg', 'camiseta-bloco-sativa-4.jpg', 'camiseta-bloco-sativa-5.jpg'];
$new_files = array_values(array_filter(array_map(static fn($f) => basename($f->getFileUri()), $assets), static fn(string $n): bool => !in_array($n, $known_files, TRUE)));
$assert(!$new_files, 'No managed images/PDFs/logos beyond the known catalog baseline (DT-O10). Novos: ' . implode(', ', $new_files));
file_put_contents(__DIR__ . '/institution/FINAL-CONTENT-MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "Content manifest written without credentials or submissions.\n";

<?php
/**
 * Importa a publicação "Bloco Sativa é objeto de Pesquisa na FIOCRUZ" para a Revista (sessão Editorial).
 *
 * Origem: https://coletivo420.org.br/editorial/2026/bloco-sativa-e-objeto-de-pesquisa-na-fiocruz/
 * Conteúdo: scripts/content/editorial/revista-bloco-sativa-fiocruz.json (texto integral da origem).
 *
 * Padrão das publicações reais da Revista (nids 71 e 86): tipo article, sessão em field_editorial_category,
 * autor editorial em field_editorial_author, origem em field_domain_source e metatag com o resumo.
 * A imagem não é importada: a origem usa um placeholder sem arquivo real.
 *
 * Idempotente: localiza a publicação pelo título e tipo. Ao salvar, o aculta_magazine cria o tópico do Fórum
 * automaticamente (comportamento da Revista, não deste script).
 *
 * Por padrão, apenas mostra o plano. Para gravar: ACULTA_APPLY=1.
 * Execução: php vendor/drush/drush/drush.php --uri=<host> scr scripts/content/editorial/import-revista-bloco-sativa-fiocruz.php
 */

use Drupal\node\Entity\Node;

$data = json_decode(file_get_contents(DRUPAL_ROOT . '/../scripts/content/editorial/revista-bloco-sativa-fiocruz.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$apply = getenv('ACULTA_APPLY') === '1';
$storage = \Drupal::entityTypeManager()->getStorage('node');

$body = '';
foreach ($data['body'] as [$tag, $text]) {
  $body .= '<' . $tag . '>' . htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</' . $tag . '>';
}
$body .= '<p>' . htmlspecialchars($data['credits'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . ' A obra completa está disponível para download '
  . '<a href="' . htmlspecialchars($data['download_url'], ENT_QUOTES, 'UTF-8') . '">aqui</a>.</p>';

$existing = $storage->loadByProperties(['type' => 'article', 'title' => $data['title']]);
$node = $existing ? reset($existing) : Node::create(['type' => 'article']);
$action = $existing ? 'ATUALIZAR nid ' . $node->id() : 'CRIAR';

$published = new DateTimeImmutable($data['published_at_utc'], new DateTimeZone('UTC'));
$node->setTitle($data['title']);
$node->setOwnerId($data['uid']);
$node->setPublished();
$node->setCreatedTime($published->getTimestamp());
$node->set('body', ['value' => $body, 'format' => 'full_html']);
$node->set('field_subtitle', $data['subtitle']);
$node->set('field_summary', $data['summary']);
$node->set('field_editorial_category', ['target_id' => $data['editorial_category_tid']]);
$node->set('field_editorial_author', ['target_id' => $data['editorial_author_tid']]);
$node->set('field_domain_source', $data['domain_source']);
$node->set('field_references', $data['references']);
$node->set('field_published_at', $published->format('Y-m-d\TH:i:s'));
$node->set('field_meta_tags', json_encode(['description' => $data['summary'], 'og_description' => $data['summary']], JSON_UNESCAPED_UNICODE));

// Capa: arte já existente no tema (mesma origem do projeto do bloco), copiada para public:// e ligada à mídia.
// A origem não tem imagem real (placeholder), então a capa é uma escolha do projeto e pode ser trocada na edição.
$cover = $data['cover'];
$coverSrc = DRUPAL_ROOT . '/../' . $cover['source'];
$mediaStorage = \Drupal::entityTypeManager()->getStorage('media');
$existingMedia = $mediaStorage->loadByProperties(['name' => $cover['name'], 'bundle' => 'image']);
echo "Capa: " . basename($coverSrc) . ($existingMedia ? " (mídia existente " . reset($existingMedia)->id() . ")" : " (nova mídia)") . "\n";

echo ($apply ? "Modo gravação\n" : "Dry-run: nada será gravado (ACULTA_APPLY=1 grava)\n");
echo "$action: \"{$data['title']}\" (autor uid {$data['uid']}, sessão tid {$data['editorial_category_tid']}, "
  . "publicada em {$published->format('Y-m-d H:i')} UTC, corpo com " . count($data['body']) . " blocos)\n";

if ($apply) {
  $fileRepo = \Drupal::service(\Drupal\file\FileRepositoryInterface::class);
  $fs = \Drupal::service('file_system');
  $dest = 'public://editorial/revista/' . basename($coverSrc);
  $dir = dirname($dest);
  $fs->prepareDirectory($dir, $fs::CREATE_DIRECTORY);
  $file = $fileRepo->writeData(file_get_contents($coverSrc), $dest, $fs::EXISTS_REPLACE);
  $media = $existingMedia ? reset($existingMedia) : \Drupal\media\Entity\Media::create(['bundle' => 'image', 'name' => $cover['name'], 'uid' => $data['uid'], 'status' => 1]);
  $media->set('field_media_image', ['target_id' => $file->id(), 'alt' => $cover['alt'], 'title' => $cover['name']]);
  $media->save();
  $node->set('field_media_image', ['target_id' => $media->id()]);
  $node->save();
  echo "Salvo nid {$node->id()} com capa (mídia {$media->id()}, arquivo {$file->id()})\n";
}

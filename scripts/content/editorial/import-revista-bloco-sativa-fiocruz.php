<?php
/**
 * Importa a publicação "Bloco Sativa é objeto de Pesquisa na FIOCRUZ" para a Revista (sessão Editorial).
 *
 * Origem: https://coletivo420.org.br/editorial/2026/bloco-sativa-e-objeto-de-pesquisa-na-fiocruz/
 * Conteúdo: scripts/content/editorial/revista-bloco-sativa-fiocruz.json (texto integral e imagens da origem).
 *
 * Padrão das publicações reais da Revista (nids 71 e 86): sessão em field_editorial_category, autor editorial em
 * field_editorial_author, origem em field_domain_source e metatag com o resumo. Imagens: cada entrada de "images"
 * vira uma mídia do bundle `image` (arquivo em public://editorial/revista). A capa vai em field_media_image e
 * as imagens do corpo entram como <drupal-media> (mesmo formato das publicações existentes).
 *
 * Idempotente: a publicação é localizada pelo título e tipo; as mídias, pelo nome.
 *
 * Por padrão, apenas mostra o plano (nenhuma imagem é baixada). Para gravar: ACULTA_APPLY=1.
 * Execução: php vendor/drush/drush/drush.php --uri=<host> scr scripts/content/editorial/import-revista-bloco-sativa-fiocruz.php
 */

use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\file\FileRepositoryInterface;

$data = json_decode(file_get_contents(DRUPAL_ROOT . '/../scripts/content/editorial/revista-bloco-sativa-fiocruz.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$apply = getenv('ACULTA_APPLY') === '1';
$storage = \Drupal::entityTypeManager()->getStorage('node');
$mediaStorage = \Drupal::entityTypeManager()->getStorage('media');
$fs = \Drupal::service('file_system');
$fileRepo = \Drupal::service(FileRepositoryInterface::class);

/**
 * Cria ou reaproveita a mídia de uma imagem da origem. No dry-run, só informa o plano.
 */
$ensureMedia = function (string $key) use ($data, $apply, $mediaStorage, $fs, $fileRepo, &$mediaCache): ?Media {
  $img = $data['images'][$key];
  $existing = $mediaStorage->loadByProperties(['name' => $img['name'], 'bundle' => 'image']);
  if (!$apply) {
    echo "IMAGEM [$key]: {$img['url']} -> " . ($existing ? 'mídia existente ' . reset($existing)->id() : 'nova mídia') . "\n";
    return NULL;
  }
  if (isset($mediaCache[$key])) {
    return $mediaCache[$key];
  }
  $bytes = file_get_contents($img['url']);
  if ($bytes === FALSE || @getimagesizefromstring($bytes) === FALSE) {
    throw new \RuntimeException("Imagem inválida ou inacessível: {$img['url']}");
  }
  $dest = 'public://editorial/revista/' . basename(parse_url($img['url'], PHP_URL_PATH));
  $dir = dirname($dest);
  $fs->prepareDirectory($dir, $fs::CREATE_DIRECTORY);
  $file = $fileRepo->writeData($bytes, $dest, $fs::EXISTS_REPLACE);
  $media = $existing ? reset($existing) : Media::create(['bundle' => 'image', 'name' => $img['name'], 'uid' => $data['uid'], 'status' => 1]);
  $media->set('field_media_image', ['target_id' => $file->id(), 'alt' => $img['alt'], 'title' => $img['name']]);
  $media->save();
  echo "IMAGEM [$key]: mídia {$media->id()}, arquivo {$file->id()}\n";
  return $mediaCache[$key] = $media;
};
$mediaCache = [];

$body = '';
foreach ($data['body'] as [$tag, $text]) {
  if ($tag === 'image') {
    $media = $ensureMedia($text);
    $uuid = $media ? $media->uuid() : 'DRY-RUN';
    $body .= '<drupal-media data-entity-type="media" data-entity-uuid="' . $uuid . '" data-view-mode="default"></drupal-media>';
    continue;
  }
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

echo ($apply ? "Modo gravação\n" : "Dry-run: nada será gravado nem baixado (ACULTA_APPLY=1 grava)\n");
echo "$action: \"{$data['title']}\" (autor uid {$data['uid']}, sessão tid {$data['editorial_category_tid']}, "
  . "publicada em {$published->format('Y-m-d H:i')} UTC, corpo com " . count($data['body']) . " blocos)\n";

$cover = $ensureMedia($data['cover']);
if ($apply) {
  $node->set('field_media_image', ['target_id' => $cover->id()]);
  $node->save();
  echo "Salvo nid {$node->id()}\n";
}

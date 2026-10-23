<?php

/**
 * @file
 * Mede acesso, grants e anexos da fixture F-GROUP no Runtime de testes.
 */

declare(strict_types=1);

/**
 * @file
 * Mede acesso, grants e anexos da fixture F-GROUP no Runtime de testes.
 *
 * Somente leitura sobre o conteúdo preexistente. O Comment/Media/File de
 * anexo criado aqui é removido no fim da própria medição.
 *
 * Uso:
 *   drush php:script tests/runtime/fgroup-measure.php
 */

use Drupal\Core\File\FileSystemInterface;
use Drupal\comment\Entity\Comment;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

const FGROUP_TOPIC_TITLE = '[TESTE F-GROUP] Tópico restrito';
const FGROUP_PUBLIC_TOPIC_TITLE = '[TESTE F-GROUP] Tópico público';
const FGROUP_PREFIX = 'fgroup_teste_';

$entity_type_manager = \Drupal::entityTypeManager();
$database = \Drupal::database();

$topic_ids = $entity_type_manager->getStorage('node')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'aculta_forum_topic')
  ->condition('title', FGROUP_TOPIC_TITLE)
  ->execute();
$topic = $entity_type_manager->getStorage('node')->load(reset($topic_ids));
if (!$topic instanceof Node) {
  echo json_encode(['error' => 'tópico da fixture não encontrado']) . PHP_EOL;
  return;
}

$public_topic_ids = $entity_type_manager->getStorage('node')->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'aculta_forum_topic')
  ->condition('title', FGROUP_PUBLIC_TOPIC_TITLE)
  ->execute();
$public_topic = $entity_type_manager->getStorage('node')->load(reset($public_topic_ids));

$accounts = ['anonimo' => new AnonymousUserSession()];
foreach (['ativo_membro', 'ativo_sem_membership', 'suspenso_membro', 'comum'] as $suffix) {
  $user = user_load_by_name(FGROUP_PREFIX . $suffix);
  $accounts[$suffix] = $user;
}

$public_node_access = [];
if ($public_topic instanceof Node) {
  $public_node_access = $database->select('node_access', 'na')
    ->fields('na', ['realm', 'gid', 'grant_view', 'grant_update', 'grant_delete'])
    ->condition('nid', $public_topic->id())
    ->execute()
    ->fetchAll(FetchAs::Associative);
}

$result = [
  'topic' => (int) $topic->id(),
  'node_access' => $database->select('node_access', 'na')
    ->fields('na', ['realm', 'gid', 'grant_view', 'grant_update', 'grant_delete'])
    ->condition('nid', $topic->id())
    ->execute()
    ->fetchAll(FetchAs::Associative),
  'public_topic' => $public_topic instanceof Node ? (int) $public_topic->id() : NULL,
  'public_node_access' => $public_node_access,
  'access' => [],
  'public_access' => [],
  'listing' => [],
  'listing_all' => [],
];

foreach ($accounts as $label => $account) {
  $result['access'][$label] = [
    'uid' => (int) $account->id(),
    'can_view' => $topic->access('view', $account, TRUE)->isAllowed(),
    'access_content' => $account->hasPermission('access content'),
  ];
  $query = $database->select('node', 'n');
  $query->fields('n', ['nid']);
  $query->condition('n.nid', $topic->id());
  $query->addTag('node_access');
  $query->addMetaData('account', $account);
  $result['listing'][$label] = array_map('intval', $query->execute()->fetchCol());

  $all_query = $database->select('node', 'n');
  $all_query->fields('n', ['nid']);
  $all_query->addTag('node_access');
  $all_query->addMetaData('account', $account);
  $result['listing_all'][$label] = array_map('intval', $all_query->execute()->fetchCol());

  if ($public_topic instanceof Node) {
    $result['public_access'][$label] = [
      'uid' => (int) $account->id(),
      'can_view' => $public_topic->access('view', $account, TRUE)->isAllowed(),
    ];
  }
}

// Anexo fail closed: media privada embutida em Comment do tópico restrito.
$author = $accounts['ativo_membro'];
$file = File::create([
  'uri' => 'private://forum/anexos/fgroup-teste.pdf',
  'filename' => 'fgroup-teste.pdf',
  'filemime' => 'application/pdf',
  'uid' => 1,
]);
$directory = 'private://forum/anexos';
\Drupal::service('file_system')->prepareDirectory(
  $directory,
  FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
);
file_put_contents($file->getFileUri(), 'fixture F-GROUP');
$file->setPermanent();
$file->save();
$image_file = File::create([
  'uri' => 'private://forum/anexos/fgroup-gallery.png',
  'filename' => 'fgroup-gallery.png',
  'filemime' => 'image/png',
  'uid' => 1,
]);
$image_file->setPermanent();
$image_file->save();
$media = Media::create([
  'bundle' => 'forum_attachment',
  'name' => 'fgroup-teste.pdf',
  'uid' => 1,
  'status' => 0,
  'field_media_forum_attachment' => ['target_id' => $file->id()],
  'field_media_forum_image' => ['target_id' => $image_file->id()],
]);
$media->save();
$comment = Comment::create([
  'comment_type' => 'aculta_discussion_post',
  'entity_type' => 'node',
  'entity_id' => $topic->id(),
  'field_name' => 'field_aculta_discussion',
  'uid' => 1,
  'subject' => '[TESTE F-GROUP] Anexo',
  'comment_body' => [
    'value' => sprintf('<drupal-media data-entity-type="media" data-entity-uuid="%s"></drupal-media>', $media->uuid()),
    'format' => 'plain_text',
  ],
  'status' => 1,
]);
$comment->save();
\Drupal::service('aculta_forum.attachment_publication')->syncComment($comment);

$loaded_media = $entity_type_manager->getStorage('media')->loadUnchanged($media->id());
$result['attachment'] = [
  'media' => (int) $media->id(),
  'comment' => (int) $comment->id(),
  'comment_status' => (int) $comment->isPublished(),
  'media_object_status' => (int) $media->isPublished(),
  'published' => $loaded_media->isPublished(),
  'public_reference_anon' => \Drupal::service('aculta_forum.attachment_publication')
    ->hasCurrentPublicReference($media),
  'file_exists' => ($pdf_path = \Drupal::service('file_system')->realpath($file->getFileUri())) !== FALSE && file_exists($pdf_path),
  'image_file_exists' => ($image_path = \Drupal::service('file_system')->realpath($image_file->getFileUri())) !== FALSE && file_exists($image_path),
  'media_view' => [],
  'file_download' => [],
];
$media_accounts = [
  'anonimo' => $accounts['anonimo'],
  'ativo_membro' => $author,
  'suspenso_membro' => $accounts['suspenso_membro'],
];
foreach ($media_accounts as $label => $account) {
  $result['attachment']['media_view'][$label] = (bool) \Drupal::service('aculta_portal.domain_purpose')->runInPurpose(
    'forum',
    static fn (): bool => $loaded_media->access('view', $account, TRUE)->isAllowed(),
  );
  $result['attachment']['file_download'][$label] = (bool) \Drupal::service('aculta_portal.domain_purpose')->runInPurpose(
    'forum',
    static fn (): bool => $file->access('download', $account, TRUE)->isAllowed(),
  );
}

// Galeria embutida no mesmo tópico restrito.
$gallery = $entity_type_manager->getStorage('media_gallery')->create([
  'title' => '[TESTE F-GROUP] Galeria restrita',
  'uid' => 1,
  'status' => 0,
  'field_gallery_purpose' => 'forum',
  'images' => [['target_id' => $media->id()]],
]);
$gallery->save();
$gallery_comment = Comment::create([
  'comment_type' => 'aculta_discussion_post',
  'entity_type' => 'node',
  'entity_id' => $topic->id(),
  'field_name' => 'field_aculta_discussion',
  'uid' => 1,
  'subject' => '[TESTE F-GROUP] Galeria',
  'comment_body' => [
    'value' => sprintf('<drupal-entity data-entity-type="media_gallery" data-entity-uuid="%s"></drupal-entity>', $gallery->uuid()),
    'format' => 'plain_text',
  ],
  'status' => 1,
]);
$gallery_comment->save();
\Drupal::service('aculta_forum.gallery_publication')->syncComment($gallery_comment);

$loaded_gallery = $entity_type_manager->getStorage('media_gallery')->loadUnchanged($gallery->id());
$result['gallery'] = [
  'id' => (int) $gallery->id(),
  'published' => $loaded_gallery->isPublished(),
  'reference_anon' => \Drupal::service('aculta_forum.attachment_publication')
    ->hasCurrentPublicGalleryReference($loaded_gallery),
  'reference_member' => \Drupal::service('aculta_forum.attachment_publication')
    ->hasCurrentPublicGalleryReference($loaded_gallery, $author),
  'view' => [],
];
foreach ($media_accounts as $label => $account) {
  $result['gallery']['view'][$label] = (bool) \Drupal::service('aculta_portal.domain_purpose')->runInPurpose(
    'forum',
    static fn (): bool => $loaded_gallery->access('view', $account, TRUE)->isAllowed(),
  );
}

$gallery_comment->delete();
$gallery->delete();
$comment->delete();
$media->delete();
$file->delete();
$image_file->delete();

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

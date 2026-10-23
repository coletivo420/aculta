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

$accounts = ['anonimo' => new AnonymousUserSession()];
foreach (['ativo_membro', 'ativo_sem_membership', 'suspenso_membro', 'comum'] as $suffix) {
  $user = user_load_by_name(FGROUP_PREFIX . $suffix);
  $accounts[$suffix] = $user;
}

$result = [
  'topic' => (int) $topic->id(),
  'node_access' => $database->select('node_access', 'na')
    ->fields('na', ['realm', 'gid', 'grant_view', 'grant_update', 'grant_delete'])
    ->condition('nid', $topic->id())
    ->execute()
    ->fetchAll(FetchAs::Associative),
  'access' => [],
  'listing' => [],
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
$media = Media::create([
  'bundle' => 'forum_attachment',
  'name' => 'fgroup-teste.pdf',
  'uid' => 1,
  'status' => 0,
  'field_media_forum_attachment' => ['target_id' => $file->id()],
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

$comment->delete();
$media->delete();
$file->delete();

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

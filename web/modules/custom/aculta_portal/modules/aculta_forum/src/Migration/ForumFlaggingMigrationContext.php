<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Migration;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\node\NodeInterface;

/**
 * Scopes the presave exception for one validated Forum Flag migration row.
 */
final class ForumFlaggingMigrationContext {

  /**
   * The exact entity instance currently authorized for a migration presave.
   */
  private ?FlaggingInterface $activeFlagging = NULL;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly MigrateLookupInterface $migrateLookup,
  ) {}

  /**
   * Saves a mapped Flagging while authorizing only this entity object in hooks.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $flagging
   *   The destination Flagging populated from one migration row.
   * @param int $source_flagging_id
   *   The source Flagging ID.
   * @param int $source_comment_id
   *   The source Comment ID.
   * @param callable $save
   *   The destination save operation.
   *
   * @return mixed
   *   The destination save result.
   *
   * @throws \Drupal\migrate\MigrateException
   *   When source and destination associations are inconsistent.
   */
  public function saveImportedFlagging(FlaggingInterface $flagging, int $source_flagging_id, int $source_comment_id, callable $save): mixed {
    if ($this->activeFlagging !== NULL) {
      throw new MigrateException('Importação aninhada de denúncias não é permitida.');
    }

    $source = $this->entityTypeManager->getStorage('flagging')->load($source_flagging_id);
    $source_comment = $this->entityTypeManager->getStorage('comment')->load($source_comment_id);
    $source_topic = $source_comment instanceof CommentInterface ? $source_comment->getCommentedEntity() : NULL;
    $target_flag = $this->entityTypeManager->getStorage('flag')->load('sinalizar_post_forum');
    if ($flagging->getFlagId() !== 'sinalizar_post_forum'
      || $flagging->getFlaggableType() !== 'aculta_forum_post'
      || (bool) $flagging->get('global')->value
      || (int) $flagging->getCreatedTime() <= 0) {
      throw new MigrateException(sprintf('Flagging de destino da origem %d tem tipo, dono, escopo ou timestamp inválido.', $source_flagging_id));
    }
    $target = $flagging->getFlaggable();
    $target_topic = $target instanceof ForumPost ? $target->get('topic')->entity : NULL;
    $mapped_posts = $this->migrateLookup->lookup('aculta_forum_comment_posts', ['cid' => $source_comment_id]);
    $post_id_key = $this->entityTypeManager->getDefinition('aculta_forum_post')->getKey('id');
    $mapped_post_id = $mapped_posts[0][$post_id_key] ?? NULL;

    if (!$source instanceof FlaggingInterface
      || $source->getFlagId() !== 'sinalizar_comentario'
      || $source->getFlaggableType() !== 'comment'
      || $source->getFlaggableId() !== (string) $source_comment_id
      || !$source_comment instanceof CommentInterface
      || $source_comment->bundle() !== 'aculta_discussion_post'
      || $source_comment->getFieldName() !== 'field_aculta_discussion'
      || !$source_topic instanceof NodeInterface
      || $source_topic->bundle() !== 'aculta_forum_topic'
      || !$source_topic->hasField('field_domain_source')
      || $source_topic->get('field_domain_source')->isEmpty()
      || $this->domainPurposeManager->getPurposeForDomainId((string) $source_topic->get('field_domain_source')->target_id) !== 'forum') {
      throw new MigrateException(sprintf('Flagging de origem %d não pertence a um Comment migrável do Fórum.', $source_flagging_id));
    }

    if ((int) ($source->getOwnerId() ?? 0) !== (int) ($flagging->getOwnerId() ?? 0)
      || (string) $source->get('session_id')->value !== (string) $flagging->get('session_id')->value
      || (bool) $source->get('global')->value
      || (bool) $flagging->get('global')->value
      || (int) $source->getCreatedTime() <= 0
      || (int) $source->getCreatedTime() !== (int) $flagging->getCreatedTime()) {
      throw new MigrateException(sprintf('Flagging de origem %d tem dono, escopo ou timestamp incompatível com a configuração de destino.', $source_flagging_id));
    }

    if ((int) ($source->getOwnerId() ?? 0) === 0 && trim((string) $source->get('session_id')->value) === '') {
      throw new MigrateException(sprintf('Flagging anônimo de origem %d não tem sessão identificável.', $source_flagging_id));
    }

    if ($flagging->getFlagId() !== 'sinalizar_post_forum'
      || $flagging->getFlaggableType() !== 'aculta_forum_post'
      || !$target instanceof ForumPost
      || !$target_topic instanceof NodeInterface
      || !$target_flag instanceof FlagInterface
      || $target_flag->getFlaggableEntityTypeId() !== 'aculta_forum_post'
      || $target_flag->isGlobal()
      || !in_array('aculta_forum_post', $target_flag->getBundles(), TRUE)
      || (string) $mapped_post_id !== (string) $flagging->getFlaggableId()
      || (string) $target_topic->id() !== (string) $source_topic->id()) {
      throw new MigrateException(sprintf('Flagging de origem %d não resolve para um post no mesmo tópico público.', $source_flagging_id));
    }

    $owner_id = (int) ($source->getOwnerId() ?? 0);
    if ($owner_id > 0 && !$this->entityTypeManager->getStorage('user')->load($owner_id)) {
      throw new MigrateException(sprintf('Flagging de origem %d referencia um usuário ausente.', $source_flagging_id));
    }
    $this->assertNoDuplicateTargetFlagging($flagging, $target, $source_flagging_id);

    $this->activeFlagging = $flagging;
    try {
      return $save();
    }
    finally {
      $this->activeFlagging = NULL;
    }
  }

  /**
   * Determines whether a presave hook is running inside the scoped import.
   */
  public function authorizes(FlaggingInterface $flagging): bool {
    return $this->activeFlagging === $flagging;
  }

  /**
   * Prevents duplicate reports when source rows contain corrupt duplicates.
   */
  private function assertNoDuplicateTargetFlagging(FlaggingInterface $flagging, ForumPost $target, int $source_flagging_id): void {
    $query = $this->entityTypeManager->getStorage('flagging')->getQuery()
      ->accessCheck(FALSE)
      ->condition('flag_id', 'sinalizar_post_forum')
      ->condition('entity_type', 'aculta_forum_post')
      ->condition('entity_id', $target->id())
      ->condition('uid', (int) ($flagging->getOwnerId() ?? 0));
    if ((int) ($flagging->getOwnerId() ?? 0) === 0) {
      $query->condition('session_id', (string) $flagging->get('session_id')->value);
    }
    $existing_ids = $query->execute();
    $current_id = $flagging->id();
    foreach ($existing_ids as $existing_id) {
      if ($current_id === NULL || (string) $existing_id !== (string) $current_id) {
        throw new MigrateException(sprintf('A migração da denúncia %d encontraria uma denúncia duplicada no post destino.', $source_flagging_id));
      }
    }
  }

}

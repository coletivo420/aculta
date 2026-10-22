<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\migrate\source;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keyset-paged source for report flags on eligible public Forum Comments.
 *
 * Source rows preserve the original Flagging identity fields without exposing
 * them in command output or migration messages.
 */
#[MigrateSource(id: 'aculta_forum_comment_flagging')]
final class ForumCommentFlagging extends SourcePluginBase implements ContainerFactoryPluginInterface {

  private const BATCH_SIZE = 200;

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    MigrationInterface $migration,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): \Generator {
    $storage = $this->entityTypeManager->getStorage('flagging');
    $max_ids = $this->flaggingQuery()
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    $max_id = $max_ids === [] ? 0 : (int) reset($max_ids);
    $last_id = 0;

    while ($last_id < $max_id) {
      $ids = $this->flaggingQuery()
        ->condition('id', $last_id, '>')
        ->condition('id', $max_id, '<=')
        ->sort('id')
        ->range(0, self::BATCH_SIZE)
        ->execute();
      if ($ids === []) {
        break;
      }
      $ids = array_values($ids);
      $last_id = (int) end($ids);

      foreach ($storage->loadMultiple($ids) as $flagging) {
        $comment = $this->entityTypeManager->getStorage('comment')->load($flagging->getFlaggableId());
        $topic = $comment instanceof CommentInterface ? $comment->getCommentedEntity() : NULL;
        if (!$this->isEligibleComment($comment, $topic)) {
          continue;
        }
        yield [
          'fid' => (int) $flagging->id(),
          'cid' => (int) $comment->id(),
          'uid' => (int) ($flagging->getOwnerId() ?? 0),
          'session_id' => (string) ($flagging->get('session_id')->value ?? ''),
          'global' => (int) $flagging->get('global')->value,
          'created' => (int) $flagging->getCreatedTime(),
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'fid' => 'ID da denúncia de origem',
      'cid' => 'ID do Comment associado',
      'uid' => 'ID do usuário denunciante',
      'session_id' => 'Sessão da denúncia anônima',
      'global' => 'Escopo global da denúncia',
      'created' => 'Timestamp original da denúncia',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return ['fid' => ['type' => 'integer']];
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE): int {
    return MigrateSourceInterface::NOT_COUNTABLE;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return 'Denúncias de Comments públicos do ACULTA Forum';
  }

  /**
   * Creates the stable source query for public Forum report flags.
   */
  private function flaggingQuery(): QueryInterface {
    return $this->entityTypeManager->getStorage('flagging')->getQuery()
      ->accessCheck(FALSE)
      ->condition('flag_id', 'sinalizar_comentario')
      ->condition('entity_type', 'comment');
  }

  /**
   * Checks whether the source Comment belongs to the public Forum context.
   */
  private function isEligibleComment(?CommentInterface $comment, ?EntityInterface $topic): bool {
    if (!$comment instanceof CommentInterface || $comment->bundle() !== 'aculta_discussion_post'
      || $comment->getFieldName() !== 'field_aculta_discussion'
      || !$topic instanceof NodeInterface || $topic->bundle() !== 'aculta_forum_topic'
      || !$topic->hasField('field_domain_source') || $topic->get('field_domain_source')->isEmpty()) {
      return FALSE;
    }
    return $this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id) === 'forum';
  }

}

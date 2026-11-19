<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\migrate\source;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\flag\FlaggingInterface;
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

  /**
   * Constructs a ForumCommentFlagging object.
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array<string, mixed> $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration entity.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\aculta_portal\Domain\DomainSiteManager $siteManager
   *   The domain site manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    MigrationInterface $migration,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DomainSiteManager $siteManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   * @param array<string, mixed> $plugin_definition
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): static {
    if (!$migration instanceof MigrationInterface) {
      throw new \InvalidArgumentException('MigrationInterface é obrigatória.');
    }
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager'),
      $container->get('aculta_portal.domain_site'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return \Generator<int, array<string, mixed>, void, void>
   */
  protected function initializeIterator(): \Generator {
    $storage = $this->entityTypeManager->getStorage('flagging');
    $max_res = $this->flaggingQuery()
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    $max_ids = is_array($max_res) ? array_values($max_res) : [];
    $first_max = reset($max_ids);
    $max_id = is_numeric($first_max) ? (int) $first_max : 0;
    $last_id = 0;

    while ($last_id < $max_id) {
      $ids_res = $this->flaggingQuery()
        ->condition('id', $last_id, '>')
        ->condition('id', $max_id, '<=')
        ->sort('id')
        ->range(0, self::BATCH_SIZE)
        ->execute();
      $ids = is_array($ids_res) ? array_values($ids_res) : [];
      if ($ids === []) {
        break;
      }
      $end_id = end($ids);
      $last_id = is_numeric($end_id) ? (int) $end_id : 0;

      /** @var array<int|string> $ids */
      $flaggings = $storage->loadMultiple($ids);
      foreach ($flaggings as $flagging) {
        if (!$flagging instanceof FlaggingInterface) {
          continue;
        }
        $comment = $this->entityTypeManager->getStorage('comment')->load($flagging->getFlaggableId());
        if (!$comment instanceof CommentInterface) {
          continue;
        }
        $topic = $comment->getCommentedEntity();
        if (!$this->isEligibleComment($comment, $topic)) {
          continue;
        }
        $sessionIdVal = $flagging->get('session_id')->value;
        $globalVal = $flagging->get('global')->value;
        yield [
          'fid' => (int) $flagging->id(),
          'cid' => (int) $comment->id(),
          'uid' => (int) $flagging->getOwnerId(),
          'session_id' => is_scalar($sessionIdVal) ? (string) $sessionIdVal : '',
          'global' => is_numeric($globalVal) ? (int) $globalVal : 0,
          'created' => (int) $flagging->getCreatedTime(),
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, string>
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
   *
   * @return array<string, array<string, string>>
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
    return $this->siteManager->getSiteKeyValueForDomainId((string) $topic->get('field_domain_source')->target_id) === 'forum';
  }

}

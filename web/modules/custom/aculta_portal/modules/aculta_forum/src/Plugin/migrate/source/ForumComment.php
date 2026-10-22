<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\migrate\source;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
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
 * Keyset-paged source for eligible public Forum Comments.
 *
 * Each source row represents one Comment and includes all of its translations.
 * This keeps the source ID map at one row per Comment for the post destination.
 */
#[MigrateSource(id: 'aculta_forum_comment')]
final class ForumComment extends SourcePluginBase implements ContainerFactoryPluginInterface {

  private const COMMENT_TYPE = 'aculta_discussion_post';

  private const COMMENT_FIELD = 'field_aculta_discussion';

  private const TOPIC_BUNDLE = 'aculta_forum_topic';

  private const DEFAULT_BATCH_SIZE = 200;

  private const MAX_BATCH_SIZE = 1000;

  /**
   * Maximum number of source Comments loaded into one batch.
   */
  private int $batchSize;

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    MigrationInterface $migration,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->batchSize = (int) ($configuration['comment_batch_size'] ?? self::DEFAULT_BATCH_SIZE);
    if ($this->batchSize < 1 || $this->batchSize > self::MAX_BATCH_SIZE) {
      throw new \InvalidArgumentException(sprintf('comment_batch_size deve ficar entre 1 e %d.', self::MAX_BATCH_SIZE));
    }
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
    $storage = $this->entityTypeManager->getStorage('comment');
    $max_ids = $this->sourceCommentQuery()
      ->sort('cid', 'DESC')
      ->range(0, 1)
      ->execute();
    $max_comment_id = $max_ids === [] ? 0 : (int) reset($max_ids);
    $last_comment_id = 0;

    while ($last_comment_id < $max_comment_id) {
      $comment_ids = $this->sourceCommentQuery()
        ->condition('cid', $last_comment_id, '>')
        ->condition('cid', $max_comment_id, '<=')
        ->sort('cid')
        ->range(0, $this->batchSize)
        ->execute();
      if ($comment_ids === []) {
        break;
      }
      $comment_ids = array_values($comment_ids);
      $last_comment_id = (int) end($comment_ids);

      foreach ($storage->loadMultiple($comment_ids) as $comment) {
        if (!$comment instanceof CommentInterface) {
          continue;
        }
        $topic = $comment->getCommentedEntity();
        if (!$this->isEligibleTopic($topic)) {
          continue;
        }
        yield $this->toSourceRow($comment, $topic);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'cid' => 'ID do Comment de origem',
      'parent_cid' => 'ID do Comment pai',
      'topic_id' => 'ID do tópico Node',
      'uid' => 'ID do autor Drupal',
      'created' => 'Timestamp de criação',
      'changed' => 'Timestamp de alteração',
      'published' => 'Estado publicado do Comment',
      'rejected' => 'Indicador de rejeição histórica',
      'legacy_author_id' => 'Referência de autoria histórica',
      'first_published' => 'Timestamp da primeira publicação',
      'thread' => 'Posição hierárquica original do Comment',
      'translations' => 'Assunto e corpo agrupados por idioma',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return [
      'cid' => [
        'type' => 'integer',
      ],
    ];
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
    return 'Comments públicos do ACULTA Forum';
  }

  /**
   * Creates the stable candidate query for Forum Comments.
   */
  private function sourceCommentQuery(): QueryInterface {
    return $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', self::COMMENT_TYPE)
      ->condition('entity_type', 'node')
      ->condition('field_name', self::COMMENT_FIELD);
  }

  /**
   * Checks the public Forum topic and its configured purpose.
   */
  private function isEligibleTopic(mixed $topic): bool {
    if (!$topic instanceof NodeInterface || $topic->bundle() !== self::TOPIC_BUNDLE
      || !$topic->hasField('field_domain_source') || $topic->get('field_domain_source')->isEmpty()) {
      return FALSE;
    }
    return $this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id) === 'forum';
  }

  /**
   * Extracts one Comment and all translations without author contact data.
   *
   * @return array<string, mixed>
   *   One source row with all Comment translations grouped together.
   */
  private function toSourceRow(CommentInterface $comment, NodeInterface $topic): array {
    $translations = [];
    foreach ($comment->getTranslationLanguages() as $language) {
      $translation = $comment->getTranslation($language->getId());
      $body = $translation->get('comment_body')->first();
      $translations[$language->getId()] = [
        'subject' => (string) ($translation->get('subject')->value ?? ''),
        'body_value' => (string) ($body?->value ?? ''),
        'body_format' => (string) ($body?->format ?? ''),
      ];
    }

    $parent_id = $comment->get('pid')->target_id;
    $legacy_author_id = $comment->hasField('field_autor_legado')
      ? $comment->get('field_autor_legado')->target_id
      : NULL;
    $first_published = $comment->hasField('field_aculta_published_at')
      ? $comment->get('field_aculta_published_at')->value
      : NULL;
    $rejected = $comment->hasField('field_aculta_rejected')
      && (bool) $comment->get('field_aculta_rejected')->value;

    return [
      'cid' => (int) $comment->id(),
      'parent_cid' => $parent_id === NULL ? NULL : (int) $parent_id,
      'topic_id' => (int) $topic->id(),
      'uid' => (int) ($comment->getOwnerId() ?? 0),
      'created' => (int) $comment->getCreatedTime(),
      'changed' => (int) $comment->getChangedTime(),
      'published' => (int) $comment->isPublished(),
      'rejected' => (int) $rejected,
      'legacy_author_id' => $legacy_author_id === NULL ? NULL : (int) $legacy_author_id,
      'first_published' => $first_published === NULL ? NULL : (int) $first_published,
      'thread' => (string) $comment->get('thread')->value,
      'translations' => $translations,
    ];
  }

}

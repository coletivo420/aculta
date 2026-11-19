<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\migrate\source;

use Drupal\aculta_portal\Domain\DomainSiteManager;
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
  protected int $batchSize;

  /**
   * Constructs a ForumComment source plugin.
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
    $batchSizeVal = $configuration['comment_batch_size'] ?? self::DEFAULT_BATCH_SIZE;
    $this->batchSize = is_numeric($batchSizeVal) ? (int) $batchSizeVal : self::DEFAULT_BATCH_SIZE;
    if ($this->batchSize < 1 || $this->batchSize > self::MAX_BATCH_SIZE) {
      throw new \InvalidArgumentException(sprintf('comment_batch_size deve ficar entre 1 e %d.', self::MAX_BATCH_SIZE));
    }
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
    $storage = $this->entityTypeManager->getStorage('comment');
    $max_res = $this->sourceCommentQuery()
      ->sort('cid', 'DESC')
      ->range(0, 1)
      ->execute();
    $max_ids = is_array($max_res) ? array_values($max_res) : [];
    $first_max = reset($max_ids);
    $max_comment_id = is_numeric($first_max) ? (int) $first_max : 0;
    $last_comment_id = 0;

    while ($last_comment_id < $max_comment_id) {
      $comment_res = $this->sourceCommentQuery()
        ->condition('cid', $last_comment_id, '>')
        ->condition('cid', $max_comment_id, '<=')
        ->sort('cid')
        ->range(0, $this->batchSize)
        ->execute();
      $comment_ids = is_array($comment_res) ? array_values($comment_res) : [];
      if ($comment_ids === []) {
        break;
      }
      $end_id = end($comment_ids);
      $last_comment_id = is_numeric($end_id) ? (int) $end_id : 0;

      /** @var array<int|string> $comment_ids */
      /** @var \Drupal\comment\CommentInterface[] $comments */
      $comments = $storage->loadMultiple($comment_ids);
      foreach ($comments as $comment) {
        $topic = $comment->getCommentedEntity();
        if (!$topic instanceof NodeInterface || !$this->isEligibleTopic($topic)) {
          continue;
        }
        yield $this->toSourceRow($comment, $topic);
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
      'cid' => 'ID do Comment de origem',
      'langcode' => 'Idioma padrão do Comment',
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
   *
   * @return array<string, array<string, string>>
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
    return $this->siteManager->getSiteKeyValueForDomainId((string) $topic->get('field_domain_source')->target_id) === 'forum';
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
      $subjectVal = $translation->get('subject')->value;
      $bodyVal = $translation->get('comment_body')->value;
      $bodyFmt = $translation->get('comment_body')->format;
      $translations[$language->getId()] = [
        'subject' => is_scalar($subjectVal) ? (string) $subjectVal : '',
        'body_value' => is_scalar($bodyVal) ? (string) $bodyVal : '',
        'body_format' => is_scalar($bodyFmt) ? (string) $bodyFmt : '',
        'published' => (int) $translation->isPublished(),
      ];
    }

    $parent_id = $comment->hasParentComment()
      ? $comment->get('pid')->target_id
      : NULL;
    $legacy_author_id = $comment->hasField('field_autor_legado')
      ? $comment->get('field_autor_legado')->target_id
      : NULL;
    $first_published = $comment->hasField('field_aculta_published_at')
      ? $comment->get('field_aculta_published_at')->value
      : NULL;
    $rejectedVal = $comment->hasField('field_aculta_rejected')
      ? $comment->get('field_aculta_rejected')->value
      : NULL;
    $rejected = !empty($rejectedVal);

    $threadVal = $comment->get('thread')->value;

    return [
      'cid' => (int) $comment->id(),
      'langcode' => $comment->language()->getId(),
      'parent_cid' => is_numeric($parent_id) ? (int) $parent_id : NULL,
      'topic_id' => (int) $topic->id(),
      'uid' => (int) ($comment->getOwnerId() ?? 0),
      'created' => (int) $comment->getCreatedTime(),
      'changed' => (int) $comment->getChangedTime(),
      'published' => (int) $comment->isPublished(),
      'rejected' => (int) $rejected,
      'legacy_author_id' => is_numeric($legacy_author_id) ? (int) $legacy_author_id : NULL,
      'first_published' => is_numeric($first_published) ? (int) $first_published : NULL,
      'thread' => is_scalar($threadVal) ? (string) $threadVal : '',
      'translations' => $translations,
    ];
  }

}

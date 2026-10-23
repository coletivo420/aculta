<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\search_api\processor;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeGrantsHelper;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds public Forum metadata and host Node grants to the search index.
 */
#[SearchApiProcessor(
  id: 'forum_post_search_access',
  label: new TranslatableMarkup('ACULTA Forum post and topic access'),
  description: new TranslatableMarkup('Indexes public Forum post metadata and applies host Node grants before paging.'),
  stages: [
    'add_properties' => 0,
    'pre_index_save' => -10,
    'preprocess_query' => -40,
  ],
)]
final class ForumPostSearchAccess extends ProcessorPluginBase implements ContainerFactoryPluginInterface {

  private const INDEX_ID = 'aculta_forum';

  private const TOPIC_BUNDLE = 'aculta_forum_topic';

  /**
   * Constructs the processor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?object $nodeGrantsHelper,
    private readonly Connection $database,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->has(NodeGrantsHelper::class) ? $container->get(NodeGrantsHelper::class) : NULL,
      $container->get('database'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    return $index->id() === self::INDEX_ID;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if ($datasource !== NULL) {
      return [];
    }

    $properties = [
      'forum_search_type' => ['string', FALSE],
      'forum_search_domain' => ['string', FALSE],
      'forum_search_category' => ['integer', TRUE],
      'forum_search_topic_id' => ['integer', FALSE],
      'forum_search_topic_published' => ['boolean', FALSE],
      'forum_search_post_published' => ['boolean', FALSE],
      'forum_search_topic_grants' => ['string', TRUE],
    ];
    $definitions = [];
    foreach ($properties as $name => [$type, $is_list]) {
      $definitions[$name] = new ProcessorProperty([
        'label' => new TranslatableMarkup('Forum search: @field', ['@field' => $name]),
        'description' => new TranslatableMarkup('Metadata and access values for public Forum search.'),
        'type' => $type,
        'processor_id' => $this->getPluginId(),
        'hidden' => TRUE,
        'is_list' => $is_list,
      ]);
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function preIndexSave(): void {
    foreach ([
      'forum_search_type' => 'string',
      'forum_search_domain' => 'string',
      'forum_search_category' => 'integer',
      'forum_search_topic_id' => 'integer',
      'forum_search_topic_published' => 'boolean',
      'forum_search_post_published' => 'boolean',
      'forum_search_topic_grants' => 'string',
    ] as $property => $type) {
      $this->ensureField(NULL, $property, $type)->setHidden();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items): void {
    foreach ($items as $item_id => $item) {
      $entity = $item->getOriginalObject()->getValue();
      if (!$entity instanceof ForumPost) {
        continue;
      }
      $topic = $this->getTopic($entity);
      if (!$this->isPublicForumTopic($topic) || !$topic->isPublished() || !$entity->isPublished()) {
        unset($items[$item_id]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    if ($entity instanceof NodeInterface && $entity->bundle() === self::TOPIC_BUNDLE) {
      $topic = $entity;
      $type = self::TOPIC_BUNDLE;
      $post_published = TRUE;
    }
    elseif ($entity instanceof ForumPost) {
      $topic = $this->getTopic($entity);
      $type = 'aculta_forum_post';
      $post_published = $entity->isPublished();
    }
    else {
      return;
    }

    if (!$this->isPublicForumTopic($topic)) {
      return;
    }

    $values = [
      'forum_search_type' => $type,
      'forum_search_domain' => (string) $topic->get('field_domain_source')->target_id,
      'forum_search_topic_id' => (int) $topic->id(),
      'forum_search_topic_published' => $topic->isPublished(),
      'forum_search_post_published' => $post_published,
      'forum_search_topic_grants' => $this->topicViewGrants($topic),
    ];
    foreach ($values as $property => $property_values) {
      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), NULL, $property);
      foreach ($fields as $field) {
        foreach (is_array($property_values) ? $property_values : [$property_values] as $value) {
          $field->addValue($value);
        }
      }
    }

    if ($topic->hasField('field_aculta_categories') && !$topic->get('field_aculta_categories')->isEmpty()) {
      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), NULL, 'forum_search_category');
      foreach ($fields as $field) {
        foreach ($topic->get('field_aculta_categories')->getValue() as $category) {
          if (!empty($category['target_id'])) {
            $field->addValue((int) $category['target_id']);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query): void {
    if ($query->getIndex()->id() !== self::INDEX_ID) {
      return;
    }

    $account = $query->getOption('search_api_access_account', $this->currentUser);
    if (is_numeric($account)) {
      $account = $this->entityTypeManager->getStorage('user')->load((int) $account);
    }
    if (!$account instanceof AccountInterface) {
      $query->abort('A consulta do Fórum exige uma conta válida para verificar grants.');
      return;
    }
    if (!$query->getOption('search_api_bypass_access') && !$account->hasPermission('access content')) {
      $query->abort('A conta não pode acessar conteúdo público do Fórum.');
      return;
    }

    $allowed = $query->createAndAddConditionGroup('OR', ['aculta_forum_post_access']);
    $node_items = $query->createConditionGroup();
    $node_items->addCondition('search_api_datasource', 'entity:node');
    $allowed->addConditionGroup($node_items);

    $post_items = $query->createConditionGroup();
    $post_items->addCondition('search_api_datasource', 'entity:aculta_forum_post');
    $post_items->addCondition('forum_search_topic_published', TRUE);
    $post_items->addCondition('forum_search_post_published', TRUE);
    if (!$query->getOption('search_api_bypass_access') && !$account->hasPermission('bypass node access')) {
      $grant_values = ['node_access__all'];
      // NodeGrantsHelper is the current API from Core 11.4 onward. The Forum
      // still supports Core 11.3, where only node_access_grants() is available;
      // keep that deprecated path version-gated and unreachable on newer Core.
      $grants = DeprecationHelper::backwardsCompatibleCall(
        \Drupal::VERSION,
        '11.4.0',
        fn (): array => $this->nodeGrantsHelper->nodeAccessGrants('view', $account),
        fn (): array => node_access_grants('view', $account),
      );
      foreach ($grants as $realm => $gids) {
        foreach ($gids as $gid) {
          $grant_values[] = sprintf('node_access_%s:%s', $realm, $gid);
        }
      }
      $post_items->addCondition('forum_search_topic_grants', array_values(array_unique($grant_values)), 'IN');
    }
    $allowed->addConditionGroup($post_items);
  }

  /**
   * Resolves a post's host topic.
   */
  private function getTopic(ForumPost $post): ?NodeInterface {
    if (!$post->hasField('topic') || $post->get('topic')->isEmpty()) {
      return NULL;
    }
    $topic = $post->get('topic')->entity;
    return $topic instanceof NodeInterface ? $topic : NULL;
  }

  /**
   * Checks the Forum topic bundle and Domain purpose.
   */
  private function isPublicForumTopic(?NodeInterface $topic): bool {
    return $topic instanceof NodeInterface
      && $topic->bundle() === self::TOPIC_BUNDLE
      && $topic->hasField('field_domain_source')
      && !$topic->get('field_domain_source')->isEmpty()
      && $this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id) === 'forum';
  }

  /**
   * Gets the view grants stored for a host topic.
   *
   * @return string[]
   *   Node grant values in the format used by Search API's content access
   *   processor.
   */
  private function topicViewGrants(NodeInterface $topic): array {
    $query = $this->database->select('node_access', 'na')
      ->fields('na', ['realm', 'gid'])
      ->condition('grant_view', 1);
    $ids = $query->orConditionGroup()
      ->condition('nid', 0)
      ->condition('nid', (int) $topic->id());
    $records = $query->condition($ids)->execute();
    $grants = [];
    foreach ($records as $record) {
      $grants[] = sprintf('node_access_%s:%s', $record->realm, $record->gid);
    }
    return $grants !== [] ? array_values(array_unique($grants)) : ['node_access__all'];
  }

}

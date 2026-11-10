<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_forum\Analytics\ForumActivityLedger;
use Drupal\aculta_forum\Hook\ForumActivityEventHooks;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group as GroupEntity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prova a integração real Fórum -> evento -> subscriber -> agregado.
 *
 * Os dois módulos reais são habilitados no container, a entidade oficial é
 * salva, os hooks e o EventDispatcher reais são acionados e a linha é lida na
 * tabela do Statistics. Não usa mock no caminho de gravação.
 */
#[Group('aculta_statistics')]
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumActivityIntegrationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'aculta_document_viewer',
    'aculta_file_uploader',
    'aculta_forum',
    'aculta_gallery',
    'aculta_image',
    'aculta_mail_confirmer',
    'aculta_mailer',
    'aculta_portal',
    'aculta_portal_cep',
    'aculta_statistics',
    'aculta_user',
    'address',
    'block',
    'ckeditor5',
    'comment',
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_payment',
    'commerce_price',
    'commerce_store',
    'content_moderation',
    'crop',
    'csp',
    'ctools',
    'ctools_views',
    'datetime',
    'diff',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'editor',
    'embed',
    'entity',
    'entity_embed',
    'entity_reference_revisions',
    'entity_usage',
    'field',
    'file',
    'filter',
    'flag',
    'group',
    'history',
    'image',
    'image_widget_crop',
    'inline_entity_form',
    'key',
    'link',
    'login_emailusername',
    'media',
    'media_duplicates',
    'media_gallery',
    'media_library',
    'metatag',
    'migrate',
    'node',
    'options',
    'path',
    'path_alias',
    'pathauto',
    'photoswipe',
    'physical',
    'profile',
    'reporting',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_metatag',
    'schema_organization',
    'schema_product',
    'schema_web_page',
    'schema_web_site',
    'search_api',
    'search_api_db',
    'social_api',
    'social_auth',
    'state_machine',
    'system',
    'taxonomy',
    'text',
    'token',
    'user',
    'views',
    'views_bulk_operations',
    'vvj_core',
    'vvjs',
    'workflows',
  ];

  /**
   * Arquivo de agregados.
   */
  private AggregateStorageInterface $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('aculta_forum_post');
    $this->installEntitySchema('profile');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('aculta_forum', [ForumActivityLedger::TABLE]);
    $this->installSchema('aculta_user', ['aculta_user_nickname', 'aculta_user_agreement']);
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('aculta_statistics', [DailyAggregateStorage::TABLE]);
    $this->installConfig([
      'system',
      'user',
      'field',
      'filter',
      'text',
      'node',
      'comment',
      'profile',
    ]);

    // domain_source cria a instância do campo em cada novo bundle; o storage
    // precisa existir antes do tipo de conteúdo.
    FieldStorageConfig::create([
      'field_name' => 'field_aculta_categories',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_domain_source',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => 1,
      'settings' => ['target_type' => 'domain'],
    ])->save();
    NodeType::create([
      'type' => 'aculta_forum_topic',
      'name' => 'Tópico do Fórum',
    ])->save();

    Domain::create([
      'id' => 'forum_aculta_org',
      'hostname' => 'forum.test',
      'name' => 'Fórum',
      'scheme' => 'http',
      'status' => TRUE,
      'weight' => 0,
    ])->save();

    $this->storage = $this->container->get('aculta_statistics.storage');
  }

  /**
   * Uma publicação lógica gera exatamente um incremento.
   */
  public function testPublishedTopicCountsOnce(): void {
    $topic = $this->topic(published: TRUE);
    $today = $this->storage->currentDay();

    self::assertSame(1, $this->storage->total('forum.topics.created', $today, $today));

    // Replay do mesmo hook, como numa requisição concorrente ou replay de
    // evento, não incrementa de novo.
    $hooks = $this->container->get(ForumActivityEventHooks::class);
    $hooks->topicCreated($topic);

    self::assertSame(1, $this->storage->total('forum.topics.created', $today, $today));
    self::assertSame(1, $this->ledgerCount());
  }

  /**
   * Rascunho só conta na publicação; despublicar e republicar não reemite.
   */
  public function testDraftPublishAndRepublishDoNotDuplicate(): void {
    $topic = $this->topic(published: FALSE);
    $today = $this->storage->currentDay();
    self::assertSame(0, $this->storage->total('forum.topics.created', $today, $today));

    $topic->setPublished();
    $topic->save();
    self::assertSame(1, $this->storage->total('forum.topics.created', $today, $today));

    $topic->setUnpublished();
    $topic->save();
    $topic->setPublished();
    $topic->save();

    self::assertSame(1, $this->storage->total('forum.topics.created', $today, $today));
    self::assertSame(1, $this->ledgerCount());
  }

  /**
   * Retratos contam apenas tópicos públicos e excluem Group restrito.
   */
  public function testSnapshotExcludesRestrictedGroupAndDraft(): void {
    $this->topic(published: TRUE);
    $this->topic(published: TRUE);
    $this->topic(published: FALSE);

    $this->config('aculta_forum.settings')
      ->set('restricted_group_type', 'restricted_forum')
      ->save();
    // Evita o post-install de membership do GroupType no ambiente mínimo:
    // a configuração do tipo é o que o resolvedor consulta.
    $this->container->get('config.factory')->getEditable('group.type.restricted_forum')->setData([
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'restricted_forum',
      'label' => 'Fórum restrito',
      'description' => '',
      'new_revision' => FALSE,
      'creator_membership' => FALSE,
      'creator_roles' => [],
    ])->save();
    $group = GroupEntity::create([
      'type' => 'restricted_forum',
      'label' => 'Associados',
    ]);
    $group->save();
    $restricted = $this->topic(published: TRUE);
    // Relação gravada diretamente: o package de Group no ambiente mínimo
    // não instala o tipo de relação, mas o resolvedor consulta as mesmas
    // tabelas group_relationship(_field_data) em produção.
    $database = $this->container->get('database');
    $database->insert('group_relationship')
      ->fields([
        'uuid' => $this->container->get('uuid')->generate(),
        'type' => 'group_node:aculta_forum_topic',
        'langcode' => 'en',
      ])
      ->execute();
    $rid = (int) $database->lastInsertId();
    $database->insert('group_relationship_field_data')
      ->fields([
        'id' => $rid,
        'type' => 'group_node:aculta_forum_topic',
        'langcode' => 'en',
        'default_langcode' => 1,
        'gid' => $group->id(),
        'entity_id' => $restricted->id(),
        'plugin_id' => 'group_node:aculta_forum_topic',
        'label' => '',
        'uid' => 0,
        'created' => $this->container->get('datetime.time')->getRequestTime(),
        'changed' => $this->container->get('datetime.time')->getRequestTime(),
      ])
      ->execute();

    $day = $this->storage->currentDay();
    $provider = $this->container->get('aculta_forum.metrics_provider');
    self::assertSame(2, $provider->value('forum.topics.published_current', $day));
    self::assertSame(2, $provider->value('forum.topics.unanswered', $day));
  }

  /**
   * O ledger guarda só impressão digital e instante.
   */
  public function testLedgerNeverStoresPersonalData(): void {
    $this->topic(published: TRUE);
    $row = $this->container->get('database')->select(ForumActivityLedger::TABLE, 'l')
      ->fields('l')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    self::assertIsArray($row);
    self::assertSame(['fingerprint', 'created'], array_keys($row));
    self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $row['fingerprint']);
  }

  /**
   * Cria um tópico no domínio do Fórum.
   */
  private function topic(bool $published): Node {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico de integração',
      'status' => $published ? 1 : 0,
      'uid' => 0,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $topic->save();
    return $topic;
  }

  /**
   * Conta linhas do ledger.
   */
  private function ledgerCount(): int {
    return (int) $this->container->get('database')->select(ForumActivityLedger::TABLE, 'l')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}

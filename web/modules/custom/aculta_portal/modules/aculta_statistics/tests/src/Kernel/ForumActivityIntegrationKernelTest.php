<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_forum\Analytics\ForumActivityLedger;
use Drupal\aculta_forum\Hook\ForumActivityEventHooks;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\domain\Entity\Domain;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group as GroupEntity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
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
    $this->installEntitySchema('flagging');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('aculta_forum', [ForumActivityLedger::TABLE, 'aculta_discussion_reaction', 'aculta_discussion_reaction_user_stats']);
    $this->installSchema('aculta_user', ['aculta_user_nickname', 'aculta_user_agreement']);
    $this->installSchema('user', ['users_data']);
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
    CommentType::create([
      'id' => 'aculta_discussion_post',
      'label' => 'Resposta do Fórum',
      'target_entity_type_id' => 'node',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_aculta_discussion',
      'entity_type' => 'node',
      'type' => 'comment',
      'settings' => ['comment_type' => 'aculta_discussion_post'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_aculta_discussion',
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'label' => 'Discussão',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_nickname',
      'entity_type' => 'profile',
      'type' => 'string',
    ])->save();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    Domain::create([
      'id' => 'forum_aculta_org',
      'hostname' => 'forum.test',
      'name' => 'Fórum',
      'scheme' => 'http',
      'status' => TRUE,
      'weight' => 0,
    ])->save();

    // Contas novas não entram em moderação prévia neste cenário de métricas.
    $this->container->get('config.factory')->getEditable('aculta_user.settings')
      ->set('primeiro_comentario_discussao_moderado', FALSE)
      ->save();

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
   * Participação, primeira resposta e tópicos sem resposta.
   */
  public function testParticipationAndResponseMetrics(): void {
    $now = $this->container->get('datetime.time')->getRequestTime();
    $participants = [];
    for ($i = 1; $i <= 4; $i++) {
      $user = User::create(['name' => 'participante-' . $i, 'status' => 1]);
      $user->save();
      $participants[] = (int) $user->id();
    }
    [$u1, $u2, $u3, $u4] = $participants;

    $topicA = $this->topicAt($u1, $now - 5000);
    self::assertSame($u1, $topicA->getOwnerId());
    self::assertSame($now - 5000, $topicA->getCreatedTime());
    $topicB = $this->topicAt($u2, $now - 2 * 86400);
    $topicC = $this->topicAt($u3, $now - 86400);
    $this->topicAt($u4, $now - 80 * 3600);

    $this->comment($topicA, $u2, $now - 200);
    $this->comment($topicB, $u2, $now - 86400);
    $this->comment($topicC, $u1, $now - 86400 + 600);

    $provider = $this->container->get('aculta_forum.metrics_provider');
    $day = $this->storage->currentDay();

    $comments = $this->container->get('database')->select('comment_field_data', 'c')
      ->fields('c', ['cid', 'entity_id', 'uid', 'status', 'comment_type', 'default_langcode', 'created'])
      ->execute()
      ->fetchAll();
    self::assertCount(3, $comments);
    $comment_uids = array_map(static fn ($row): int => (int) $row->uid, $comments);
    sort($comment_uids);
    $expected_comment_uids = [$u1, $u2, $u2];
    sort($expected_comment_uids);
    self::assertSame($expected_comment_uids, $comment_uids);
    $comment_topics = array_map(static fn ($row): int => (int) $row->entity_id, $comments);
    sort($comment_topics);
    $expected_topics = [(int) $topicA->id(), (int) $topicB->id(), (int) $topicC->id()];
    sort($expected_topics);
    self::assertSame($expected_topics, $comment_topics);
    self::assertSame([1, 1, 1], array_map(static fn ($row): int => (int) $row->status, $comments));
    self::assertSame(
      ['aculta_discussion_post', 'aculta_discussion_post', 'aculta_discussion_post'],
      array_map(static fn ($row): string => (string) $row->comment_type, $comments),
    );
    $comment_created = array_map(static fn ($row): int => (int) $row->created, $comments);
    sort($comment_created);
    $expected_created = [(int) ($now - 86400), (int) ($now - 85800), (int) ($now - 200)];
    sort($expected_created);
    self::assertSame($expected_created, $comment_created);
    $repliedQ = $this->container->get('database')->select('comment_field_data', 'c');
    $repliedQ->join('node_field_data', 'cn', 'cn.nid = c.entity_id');
    $repliedQ->fields('c', ['entity_id']);
    $repliedQ->condition('c.status', 1);
    $repliedQ->condition('c.comment_type', 'aculta_discussion_post');
    $repliedQ->where('c.uid <> cn.uid');
    $repliedIds = array_map('intval', $repliedQ->execute()->fetchCol());
    sort($repliedIds);
    $expectedReplied = [(int) $topicA->id(), (int) $topicC->id()];
    sort($expectedReplied);
    self::assertSame($expectedReplied, $repliedIds);

    $rows = $this->container->get('database')->select('node_field_data', 'n')
      ->fields('n', ['nid', 'uid', 'created', 'status'])
      ->execute()
      ->fetchAll();
    self::assertCount(4, $rows);
    $uids = array_map(static fn ($row): int => (int) $row->uid, $rows);
    sort($uids);
    self::assertSame($participants, $uids);
    $dates = array_map(static fn ($row): int => (int) $row->created, $rows);
    self::assertCount(4, $dates);

    self::assertSame(4, $provider->value('forum.participants.engaged_7d', $day));
    self::assertSame(4, $provider->value('forum.participants.engaged_30d', $day));
    self::assertSame(0, $provider->value('forum.participants.new_contributors', $day));
    self::assertSame(2, $provider->value('forum.topics.unanswered', $day));
    self::assertSame(1, $provider->value('forum.response.unanswered_over_72h', $day));
    self::assertSame(10, $provider->value('forum.response.first_median_minutes', $day));
    self::assertSame(80, $provider->value('forum.response.first_p90_minutes', $day));
  }

  /**
   * Reações públicas contam uma vez, trocam sem somar e restritas ficam fora.
   */
  public function testReactionsAreScopedAndCountedOnce(): void {
    $this->container->get('config.factory')->getEditable('aculta_forum.settings')
      ->set('reactions_enabled', TRUE)
      ->set('default_reaction', 'heart')
      ->set('allowed_reactions', [
        ['id' => 'heart', 'icon' => 'heart', 'label' => 'Curtir', 'enabled' => TRUE],
        ['id' => 'fire', 'icon' => 'fire', 'label' => 'Excelente', 'enabled' => TRUE],
      ])
      ->save();

    $author = User::create(['name' => 'autora-reacao', 'status' => 1]);
    $author->save();
    $reactor = User::create(['name' => 'reagente', 'status' => 1]);
    $reactor->save();
    $topic = $this->topicAt((int) $author->id(), time());
    $manager = $this->container->get('aculta_forum.discussion_reaction_manager');
    $day = $this->storage->currentDay();

    $manager->toggleReaction($topic, (int) $reactor->id(), 'heart');
    $row = $this->container->get('database')->select('aculta_discussion_reaction', 'r')
      ->fields('r', ['reaction', 'is_public_context'])
      ->execute()
      ->fetchAssoc();
    self::assertSame('heart', $row['reaction']);
    self::assertSame(1, (int) $row['is_public_context']);
    self::assertSame(1, $this->storage->total('forum.reactions.added', $day, $day, 'heart'));
    self::assertSame(1, $manager->getUserStats((int) $reactor->id())['reactions_given']);

    // Trocar o emoji não soma `added`.
    $manager->toggleReaction($topic, (int) $reactor->id(), 'fire');
    self::assertSame(1, $this->storage->total('forum.reactions.added', $day, $day));

    // Remover zera o estoque e preserva o histórico de adição.
    $manager->toggleReaction($topic, (int) $reactor->id(), 'fire');
    self::assertSame(0, $manager->getUserStats((int) $reactor->id())['reactions_given']);
    self::assertSame(1, $this->storage->total('forum.reactions.added', $day, $day));
  }

  /**
   * Denúncias abertas deduplicam por conteúdo e posts pendentes são contados.
   */
  public function testModerationMetricsCountDistinctPublicContent(): void {
    $author = User::create(['name' => 'autora-denuncia', 'status' => 1]);
    $author->save();
    $reporter = User::create(['name' => 'denunciante', 'status' => 1]);
    $reporter->save();
    $topic = $this->topicAt((int) $author->id(), time());
    $database = $this->container->get('database');
    foreach ([$author->id(), $reporter->id()] as $uid) {
      $database->insert('flagging')
        ->fields([
          'uuid' => $this->container->get('uuid')->generate(),
          'flag_id' => 'sinalizar_topico',
          'entity_type' => 'node',
          'entity_id' => (string) $topic->id(),
          'global' => 0,
          'uid' => $uid,
          'session_id' => '',
          'created' => time(),
        ])
        ->execute();
    }

    $provider = $this->container->get('aculta_forum.metrics_provider');
    $day = $this->storage->currentDay();
    self::assertSame(1, $provider->value('forum.moderation.reports_open', $day));

    ForumPost::create([
      'uid' => (int) $author->id(),
      'topic' => $topic->id(),
      'body' => ['value' => 'Pendente', 'format' => 'plain_text'],
      'status' => 0,
    ])->save();
    self::assertSame(1, $provider->value('forum.moderation.posts_pending', $day));
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
   * Cria um tópico com autor e instante controlados.
   */
  private function topicAt(int $uid, int $created): Node {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico ' . $uid . '-' . $created,
      'status' => 1,
      'uid' => $uid,
      'created' => $created,
      'field_domain_source' => ['target_id' => 'forum_aculta_org'],
    ]);
    $topic->setOwnerId($uid);
    $topic->setCreatedTime($created);
    $topic->save();
    return $topic;
  }

  /**
   * Cria uma resposta publicada no tópico.
   */
  private function comment(Node $topic, int $uid, int $created): Comment {
    $comment = Comment::create([
      'comment_type' => 'aculta_discussion_post',
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'field_name' => 'field_aculta_discussion',
      'uid' => $uid,
      'status' => 1,
      'created' => $created,
      'subject' => 'Resposta',
    ]);
    $comment->setOwnerId($uid);
    $comment->setCreatedTime($created);
    $comment->save();
    return $comment;
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

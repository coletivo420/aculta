<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_ugc\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_ugc\Entity\UgcRecipientConfig;
use Drupal\aculta_ugc\Entity\UgcReport;
use Drupal\aculta_ugc\ModerationScope;
use Drupal\aculta_ugc\Service\ModerationNotifier;
use Drupal\aculta_ugc\Service\ReportService;
use Drupal\aculta_ugc\Service\UgcReportQueue;
use Drupal\flag\Entity\Flag;
use Drupal\flag\Entity\Flagging;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Valida notificações, fila com escopo de acesso e migração de flags (ARQ-06B, C, D).
 */
#[Group('aculta_ugc')]
#[RunTestsInSeparateProcesses]
final class UgcNotifierAndQueueKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'flag',
    'aculta_ugc',
  ];

  /**
   * Serviço de denúncias.
   */
  private ReportService $reportService;

  /**
   * Fila de moderação.
   */
  private UgcReportQueue $queue;

  /**
   * Notificador de moderação.
   */
  private ModerationNotifier $notifier;

  /**
   * Usuário denunciante.
   */
  private User $reporter;

  /**
   * Usuário moderador de fórum.
   */
  private User $forumModerator;

  /**
   * Usuário moderador transversal.
   */
  private User $globalModerator;

  /**
   * Usuário comum sem permissões.
   */
  private User $outsider;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $loader = require dirname(DRUPAL_ROOT) . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_ugc\\', dirname(__DIR__, 3) . '/src', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('flagging');
    $this->installEntitySchema('aculta_ugc_report');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['system', 'user', 'filter', 'node', 'flag']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Topic'])->save();

    Role::load('authenticated')?->grantPermission('access content')->save();

    $roleModForum = Role::create(['id' => 'mod_forum', 'label' => 'Mod Forum']);
    $roleModForum->grantPermission(ModerationScope::permissionFor('forum'));
    $roleModForum->save();

    $roleModGlobal = Role::create(['id' => 'mod_global', 'label' => 'Mod Global']);
    $roleModGlobal->grantPermission(ModerationScope::GLOBAL_PERMISSION);
    $roleModGlobal->save();

    User::create(['name' => 'root', 'mail' => 'root@example.com', 'status' => 1])->save();
    $this->reporter = User::create(['name' => 'reporter', 'mail' => 'reporter@example.com', 'status' => 1]);
    $this->reporter->save();

    $this->forumModerator = User::create(['name' => 'mod_forum', 'mail' => 'mod_forum@example.com', 'status' => 1]);
    $this->forumModerator->addRole('mod_forum');
    $this->forumModerator->save();

    $this->globalModerator = User::create(['name' => 'mod_global', 'mail' => 'mod_global@example.com', 'status' => 1]);
    $this->globalModerator->addRole('mod_global');
    $this->globalModerator->save();

    $this->outsider = User::create(['name' => 'outsider', 'mail' => 'outsider@example.com', 'status' => 1]);
    $this->outsider->save();

    $this->reportService = $this->container->get('aculta_ugc.report_service');
    $this->queue = $this->container->get('aculta_ugc.report_queue');
    $this->notifier = $this->container->get('aculta_ugc.moderation_notifier');
  }

  /**
   * Testa que o notificador não inventa endereços fictícios quando não configurado.
   */
  public function testNotifierDoesNotInventFallbackEmails(): void {
    $recipients = $this->notifier->getRecipientsForPurpose('purpose_sem_config');
    $this->assertSame([], $recipients);

    // Se configurar destinatários para o purpose, deve retornar apenas eles.
    UgcRecipientConfig::create([
      'id' => 'portal',
      'label' => 'Moderadores Portal',
      'emails' => ['mod-portal@test.org', 'invalid-mail'],
    ])->save();

    $configured = $this->notifier->getRecipientsForPurpose('portal');
    $this->assertSame(['mod-portal@test.org'], $configured);
  }

  /**
   * Testa aviso de denúncia com privacidade absoluta e ausência de dados do denunciante.
   */
  public function testNotificationDispatchedWithStrictPrivacy(): void {
    UgcRecipientConfig::create([
      'id' => 'forum',
      'label' => 'Moderadores Forum',
      'emails' => ['forum-mod@test.org'],
    ])->save();

    $topic = Node::create(['type' => 'aculta_forum_topic', 'title' => 'Topico Suspeito', 'status' => 1]);
    $topic->save();

    $report = $this->reportService->createReport($topic, $this->reporter, 'spam', 'forum');
    $this->assertInstanceOf(UgcReport::class, $report);

    $dispatched = $this->notifier->getDispatchedNotifications();
    $this->assertNotEmpty($dispatched);
    $last = end($dispatched);

    $this->assertSame('forum', $last['purpose']);
    $this->assertSame('node', $last['target_type']);
    $this->assertSame((int) $topic->id(), $last['target_id']);
    $this->assertSame('spam', $last['motivo']);
    $this->assertSame(['forum-mod@test.org'], $last['recipients']);
    $this->assertArrayNotHasKey('uid', $last);
    $this->assertArrayNotHasKey('reporter', $last);
  }

  /**
   * Valida controle de acesso à fila de moderação por escopo e permissão.
   */
  public function testQueueAccessControlAndScoping(): void {
    $topic = Node::create(['type' => 'aculta_forum_topic', 'title' => 'Topico Forum', 'status' => 1]);
    $topic->save();
    $article = Node::create(['type' => 'article', 'title' => 'Artigo Portal', 'status' => 1]);
    $article->save();

    $this->reportService->createReport($topic, $this->reporter, 'spam', 'forum');
    $this->reportService->createReport($article, $this->reporter, 'ofensa', 'portal');

    // 1. Anônimo ou usuário comum sem permissão é rejeitado.
    $anonymous = User::getAnonymousUser();
    $this->expectException(AccessDeniedHttpException::class);
    $this->queue->getQueueItems($anonymous);
  }

  /**
   * Valida visibilidade seletiva da fila por purpose.
   */
  public function testQueuePurposeFiltering(): void {
    $topic = Node::create(['type' => 'aculta_forum_topic', 'title' => 'Topico Forum', 'status' => 1]);
    $topic->save();
    $article = Node::create(['type' => 'article', 'title' => 'Artigo Portal', 'status' => 1]);
    $article->save();

    $this->reportService->createReport($topic, $this->reporter, 'spam', 'forum');
    $this->reportService->createReport($article, $this->reporter, 'ofensa', 'portal');

    // 2. Moderador de fórum só vê itens de fórum.
    $forumItems = $this->queue->getQueueItems($this->forumModerator);
    $this->assertSame(1, $forumItems['total_items']);
    $this->assertSame('forum', $forumItems['items'][0]['purpose']);
    $this->assertSame((int) $topic->id(), $forumItems['items'][0]['target_id']);

    // 3. Moderador global vê todos os purposes autorizados.
    $globalItems = $this->queue->getQueueItems($this->globalModerator);
    $this->assertSame(2, $globalItems['total_items']);

    // 4. Moderador de fórum tentando acessar purpose portal explicitamente recebe 403.
    try {
      $this->queue->getQueueItems($this->forumModerator, 'portal');
      $this->fail('Moderador de forum acessou fila do portal.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertTrue(TRUE);
    }
  }

  /**
   * Valida exclusão de entidades deletadas da fila ativa.
   */
  public function testQueueExcludesDeletedTargetEntities(): void {
    $node = Node::create(['type' => 'aculta_forum_topic', 'title' => 'Topico a Deletar', 'status' => 1]);
    $node->save();

    $this->reportService->createReport($node, $this->reporter, 'spam', 'forum');
    $before = $this->queue->getQueueItems($this->globalModerator, 'forum');
    $this->assertSame(1, $before['total_items']);

    // Exclui fisicamente a entidade denunciada.
    $node->delete();

    $after = $this->queue->getQueueItems($this->globalModerator, 'forum');
    $this->assertSame(0, $after['total_items']);
  }

  /**
   * Valida migração idempotente com dry-run, preservação de vínculos e rollback.
   */
  public function testMigrationDryRunIdempotenceAndRollback(): void {
    // Configura flag legado.
    $flag = Flag::create([
      'id' => 'sinalizar_topico',
      'label' => 'Sinalizar Tópico',
      'entity_type' => 'node',
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
    ]);
    $flag->save();

    $node = Node::create(['type' => 'aculta_forum_topic', 'title' => 'Topico Sinalizado', 'status' => 1]);
    $node->save();

    $flagging = Flagging::create([
      'flag_id' => 'sinalizar_topico',
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'uid' => $this->reporter->id(),
      'created' => 1700000000,
    ]);
    $flagging->save();

    // 1. Dry run: simula sem persistir.
    $dryStats = $this->queue->migrateFromReportQueue(TRUE);
    $this->assertTrue($dryStats['dry_run']);
    $this->assertSame(1, $dryStats['flags_lidas']);
    $this->assertSame(1, $dryStats['novas_denuncias']);
    $this->assertSame(0, $dryStats['ignoradas_duplicadas']);

    // Confirma que nenhuma denúncia foi criada no dry-run.
    $reports = $this->container->get('entity_type.manager')->getStorage('aculta_ugc_report')->loadMultiple();
    $this->assertEmpty($reports);

    // 2. Execução real.
    $realStats = $this->queue->migrateFromReportQueue(FALSE);
    $this->assertFalse($realStats['dry_run']);
    $this->assertSame(1, $realStats['flags_lidas']);
    $this->assertSame(1, $realStats['novas_denuncias']);

    // Confirma gravação com legacy_flag_id preservado.
    $createdReports = $this->container->get('entity_type.manager')->getStorage('aculta_ugc_report')->loadMultiple();
    $this->assertCount(1, $createdReports);
    /** @var \Drupal\aculta_ugc\Entity\UgcReport $migrated */
    $migrated = reset($createdReports);
    $this->assertSame('sinalizacao_legada_forum', $migrated->get('motivo')->value);
    $this->assertSame((int) $flagging->id(), (int) $migrated->get('legacy_flag_id')->value);
    $this->assertSame(1700000000, (int) $migrated->get('created')->value);

    // 3. Reexecução: idempotência total (0 novas denúncias, 1 ignorada por duplicata).
    $reStats = $this->queue->migrateFromReportQueue(FALSE);
    $this->assertSame(0, $reStats['novas_denuncias']);
    $this->assertSame(1, $reStats['ignoradas_duplicadas']);

    // 4. Rollback: remove com segurança apenas as denúncias migradas.
    $removed = $this->queue->rollbackMigration();
    $this->assertSame(1, $removed);
    $remaining = $this->container->get('entity_type.manager')->getStorage('aculta_ugc_report')->loadMultiple();
    $this->assertEmpty($remaining);
  }

}

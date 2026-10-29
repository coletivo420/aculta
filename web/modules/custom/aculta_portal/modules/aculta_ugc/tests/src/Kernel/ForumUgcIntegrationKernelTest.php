<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_ugc\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_user\Associado\ActiveAssociateInterface;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\aculta_user\ParticipationRules;
use Drupal\aculta_image\FileRemover;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Symfony\Component\DependencyInjection\Reference;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_ugc\Entity\UgcRecipientConfig;
use Drupal\aculta_ugc\ModerationScope;
use Drupal\aculta_ugc\Service\ModerationNotifier;
use Drupal\aculta_ugc\Service\UgcReportQueue;
use Drupal\aculta_user\Controller\ReportQueueController;
use Drupal\flag\Entity\Flag;
use Drupal\flag\Entity\Flagging;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Valida a integração operacional entre Fórum e moderação UGC (ARQ-06F).
 */
#[Group('aculta_ugc')]
#[RunTestsInSeparateProcesses]
final class ForumUgcIntegrationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path_alias',
    'file',
    'image',
    'field',
    'text',
    'filter',
    'node',
    'comment',
    'flag',
    'migrate',
    'aculta_ugc',
    'aculta_forum',
    'aculta_forum_post_test',
  ];

  /**
   * Fila de moderação UGC.
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
   * Usuário comum sem permissões de moderação.
   */
  private User $outsider;

  /**
   * Flag de sinalização de tópico do fórum.
   */
  private Flag $flagTopic;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $root = dirname(DRUPAL_ROOT);
    $loader = require $root . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_ugc\\', dirname(__DIR__, 3) . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_portal\\', $root . '/web/modules/custom/aculta_portal/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_forum\\', $root . '/web/modules/custom/aculta_portal/modules/aculta_forum/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_user\\', $root . '/web/modules/custom/aculta_portal/modules/aculta_user/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_image\\', $root . '/web/modules/custom/aculta_portal/modules/aculta_image/src', TRUE);

    $container->register('aculta_portal.email_confirmation_policy', EmailConfirmationPolicy::class)
      ->addArgument(new Reference('user.data'))
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('module_handler'));

    $container->register('aculta_portal.domain_purpose', DomainPurposeManager::class)
      ->setFactory([self::class, 'createDummyDomainPurposeManager']);

    $container->register('aculta_image.file_remover', FileRemover::class)
      ->setFactory([self::class, 'createDummyFileRemover']);

    $container->register('aculta_user.participation_rules', ParticipationRules::class)
      ->setFactory([self::class, 'createDummyParticipationRules']);

    $container->register('aculta_user.author_card_builder', AuthorCardBuilder::class)
      ->setFactory([self::class, 'createDummyAuthorCardBuilder']);

    $container->register('aculta_user.active_associate', ForumUgcTestActiveAssociate::class);

    foreach ([
      'aculta_portal.unused_file_cleaner',
      'aculta_portal.unused_media_cleaner',
      'aculta_image.render',
      'aculta_statistics.storage',
      'entity_usage.usage',
      'node.grant_storage',
    ] as $serviceId) {
      if (!$container->has($serviceId)) {
        $container->register($serviceId, \stdClass::class);
      }
    }
  }

  /**
   * Cria instância dummy de AuthorCardBuilder para testes.
   */
  public static function createDummyAuthorCardBuilder(): AuthorCardBuilder {
    $ref = new \ReflectionClass(AuthorCardBuilder::class);
    return $ref->newInstanceWithoutConstructor();
  }

  /**
   * Cria instância dummy de ParticipationRules para testes.
   */
  public static function createDummyParticipationRules(): ParticipationRules {
    $ref = new \ReflectionClass(ParticipationRules::class);
    return $ref->newInstanceWithoutConstructor();
  }

  /**
   * Cria instância dummy de DomainPurposeManager para testes.
   */
  public static function createDummyDomainPurposeManager(): DomainPurposeManager {
    $ref = new \ReflectionClass(DomainPurposeManager::class);
    return $ref->newInstanceWithoutConstructor();
  }

  /**
   * Cria instância dummy de FileRemover para testes.
   */
  public static function createDummyFileRemover(): FileRemover {
    $ref = new \ReflectionClass(FileRemover::class);
    return $ref->newInstanceWithoutConstructor();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('flagging');
    $this->installEntitySchema('aculta_ugc_report');
    $this->installEntitySchema('aculta_legado_autor');
    $this->installEntitySchema('aculta_forum_post');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['system', 'user', 'filter', 'node', 'comment', 'flag']);

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Tópico Fórum'])->save();

    Role::load('authenticated')
      ?->grantPermission('access content')
      ?->grantPermission('report aculta forum content')
        ->save();

    $roleModForum = Role::create(['id' => 'mod_forum', 'label' => 'Mod Forum']);
    $roleModForum->grantPermission('moderar denuncias do forum');
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

    $this->flagTopic = Flag::create([
      'id' => 'sinalizar_topico',
      'label' => 'Sinalizar Tópico',
      'entity_type' => 'node',
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
    ]);
    $this->flagTopic->save();

    UgcRecipientConfig::create([
      'id' => 'forum',
      'label' => 'Equipe de Moderação do Fórum',
      'emails' => ['mod-forum@aculta.org'],
    ])->save();

    $this->queue = $this->container->get('aculta_ugc.report_queue');
    $this->notifier = $this->container->get('aculta_ugc.moderation_notifier');
  }

  /**
   * Valida que novas sinalizações do Fórum sincronizam em UGC com notificação e sem duplicidade.
   */
  public function testForumFlaggingBridgeAndNotification(): void {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Tópico Ofensivo',
      'status' => 1,
    ]);
    $topic->save();

    // Usuário autenticado denuncia o tópico criando Flagging via Entity API.
    $this->container->get('current_user')->setAccount($this->reporter);
    $flagging = Flagging::create([
      'flag_id' => 'sinalizar_topico',
      'entity_type' => 'node',
      'entity_id' => $topic->id(),
      'uid' => $this->reporter->id(),
      'created' => 1700000000,
    ]);
    $flagging->save();

    // Verifica que a denúncia foi espelhada em aculta_ugc_report.
    $reportStorage = $this->container->get('entity_type.manager')->getStorage('aculta_ugc_report');
    $reports = $reportStorage->loadByProperties([
      'legacy_flag_id' => (int) $flagging->id(),
    ]);
    $this->assertCount(1, $reports);

    /** @var \Drupal\aculta_ugc\Entity\UgcReport $report */
    $report = reset($reports);
    $this->assertSame('forum', $report->get('purpose')->value);
    $this->assertSame('node', $report->get('target_type')->value);
    $this->assertSame((int) $topic->id(), (int) $report->get('target_id')->value);
    $this->assertSame('pendente', $report->get('status')->value);

    // Valida que o aviso de moderação foi disparado com proteção de privacidade.
    $notices = $this->notifier->getDispatchedNotifications();
    $this->assertNotEmpty($notices);
    $lastNotice = end($notices);
    $this->assertSame('forum', $lastNotice['purpose']);
    $this->assertSame(['mod-forum@aculta.org'], $lastNotice['recipients']);
    $this->assertArrayNotHasKey('uid', $lastNotice);

    // Valida que se a flag for desfeita (excluída), a denúncia pendente é removida.
    $flagging->delete();
    $reloadedReports = $reportStorage->loadByProperties([
      'legacy_flag_id' => (int) $flagging->id(),
    ]);
    $this->assertEmpty($reloadedReports);
  }

  /**
   * Valida reconciliação da fila unificada entre UGC e flags legadas pendentes.
   */
  public function testUnifiedQueueReconciliation(): void {
    $topic1 = Node::create(['type' => 'aculta_forum_topic', 'title' => 'Tópico 1', 'status' => 1]);
    $topic1->save();
    $topic2 = Node::create(['type' => 'aculta_forum_topic', 'title' => 'Tópico 2', 'status' => 1]);
    $topic2->save();

    // Tópico 1 possui denúncia direta em aculta_ugc_report (nova).
    $reportStorage = $this->container->get('entity_type.manager')->getStorage('aculta_ugc_report');
    $ugcReport = $reportStorage->create([
      'target_type' => 'node',
      'target_id' => $topic1->id(),
      'uid' => $this->reporter->id(),
      'motivo' => 'spam',
      'purpose' => 'forum',
      'status' => 'pendente',
      'created' => 1700000100,
    ]);
    $ugcReport->save();

    // Tópico 2 possui sinalização legada pura na tabela flagging (simulando registro histórico).
    $this->container->get('database')->insert('flagging')
      ->fields([
        'flag_id' => 'sinalizar_topico',
        'uuid' => $this->container->get('uuid')->generate(),
        'entity_type' => 'node',
        'entity_id' => $topic2->id(),
        'uid' => $this->reporter->id(),
        'session_id' => NULL,
        'created' => 1700000200,
      ])
      ->execute();

    // Consulta fila de moderação unificada com o moderador do Fórum.
    $queueData = $this->queue->getQueueItems($this->forumModerator, 'forum');
    $this->assertSame(2, $queueData['total_items']);

    $targetIds = array_column($queueData['items'], 'target_id');
    $this->assertContains((int) $topic1->id(), $targetIds);
    $this->assertContains((int) $topic2->id(), $targetIds);
  }

  /**
   * Valida isolamento estrito de moderadores por purpose na fila.
   */
  public function testModeratorIsolationBetweenPurposes(): void {
    $topic = Node::create(['type' => 'aculta_forum_topic', 'title' => 'Tópico Fórum', 'status' => 1]);
    $topic->save();

    $reportStorage = $this->container->get('entity_type.manager')->getStorage('aculta_ugc_report');
    $reportStorage->create([
      'target_type' => 'node',
      'target_id' => $topic->id(),
      'uid' => $this->reporter->id(),
      'motivo' => 'spam',
      'purpose' => 'portal',
      'status' => 'pendente',
    ])->save();

    // Moderador com permissão apenas do Fórum não pode acessar fila do portal.
    $this->expectException(AccessDeniedHttpException::class);
    $this->queue->getQueueItems($this->forumModerator, 'portal');
  }

  /**
   * Valida que o ReportQueueController renderiza o painel unificado com a tabela.
   */
  public function testReportQueueControllerRendersUnifiedPanel(): void {
    $topic = Node::create(['type' => 'aculta_forum_topic', 'title' => 'Tópico em Moderação', 'status' => 1]);
    $topic->save();

    $reportStorage = $this->container->get('entity_type.manager')->getStorage('aculta_ugc_report');
    $reportStorage->create([
      'target_type' => 'node',
      'target_id' => $topic->id(),
      'uid' => $this->reporter->id(),
      'motivo' => 'ofensa',
      'purpose' => 'forum',
      'status' => 'pendente',
      'created' => 1700000500,
    ])->save();

    $this->container->get('current_user')->setAccount($this->forumModerator);

    $controller = ReportQueueController::create($this->container);
    $request = Request::create('/painel-administrativo/forum/denuncias');
    $build = $controller->view($request);

    $this->assertIsArray($build);
    $this->assertSame('table', $build['table']['#type']);
    $this->assertCount(1, $build['table']['#rows']);
    $this->assertArrayHasKey('#cache', $build);
    $this->assertContains('aculta_ugc_report_list', $build['table']['#rows'] ? $build['#cache']['tags'] : []);

    // Usuário comum sem permissão recebe 403.
    $this->container->get('current_user')->setAccount($this->outsider);
    try {
      $controller->view($request);
      $this->fail('Usuário comum acessou painel de moderação.');
    }
    catch (AccessDeniedHttpException) {
      $this->assertTrue(TRUE);
    }
  }

}

/**
 * Dublê de ActiveAssociateInterface para testes sem carregar aculta_user completo.
 */
class ForumUgcTestActiveAssociate implements ActiveAssociateInterface {

  /**
   * {@inheritdoc}
   */
  public function isActiveAssociate(AccountInterface $account): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function associateCacheTags(AccountInterface $account): array {
    return [];
  }

}

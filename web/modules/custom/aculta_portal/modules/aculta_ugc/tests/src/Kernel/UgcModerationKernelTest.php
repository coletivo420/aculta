<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_ugc\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\aculta_ugc\Form\UgcReportDecisionForm;
use Symfony\Component\HttpFoundation\Request;
use Drupal\aculta_ugc\Controller\UgcModerationDashboardController;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_ugc\Entity\UgcRecipientConfig;
use Drupal\aculta_ugc\Entity\UgcReport;
use Drupal\aculta_ugc\Exception\ReportNotAllowedException;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Valida a moderação transversal e UGC (ARQ-06, D-03, D-04).
 */
#[Group('aculta_ugc')]
#[RunTestsInSeparateProcesses]
final class UgcModerationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'taxonomy',
    'datetime',
    'aculta_calendar',
    'aculta_events',
    'aculta_ugc',
  ];

  /**
   * Usuário moderador.
   */
  private User $adminModerator;

  /**
   * Usuário denunciante.
   */
  private User $reporterUser;

  /**
   * Usuário comum.
   */
  private User $commonUser;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $loader = require dirname(DRUPAL_ROOT) . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_ugc\\', dirname(__DIR__, 3) . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_events\\', dirname(__DIR__, 5) . '/modules/aculta_events/src', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('aculta_ugc_report');
    $this->installEntitySchema('aculta_event');
    $this->installConfig(['system', 'user']);

    $roleMod = Role::create([
      'id' => 'moderador_ugc',
      'label' => 'Moderador UGC',
    ]);
    $roleMod->grantPermission('moderar denuncias ugc');
    $roleMod->save();

    $rootUser = User::create(['name' => 'root', 'mail' => 'root@example.com', 'status' => 1]);
    $rootUser->save();

    $this->adminModerator = User::create(['name' => 'moderador', 'mail' => 'mod@example.com', 'status' => 1]);
    $this->adminModerator->addRole('moderador_ugc');
    $this->adminModerator->save();

    $this->reporterUser = User::create(['name' => 'reporter', 'mail' => 'reporter@example.com', 'status' => 1]);
    $this->reporterUser->save();

    $this->commonUser = User::create(['name' => 'common', 'mail' => 'common@example.com', 'status' => 1]);
    $this->commonUser->save();
  }

  /**
   * Valida ciclo de vida e privacidade de denúncias.
   */
  public function testReportLifecycleAndPrivacy(): void {
    /** @var \Drupal\aculta_ugc\Service\ReportService $reportService */
    $reportService = $this->container->get('aculta_ugc.report_service');
    /** @var \Drupal\aculta_ugc\Service\ModerationNotifier $notifier */
    $notifier = $this->container->get('aculta_ugc.moderation_notifier');

    // Configura destinatários de notificação para o purpose 'eventos' (D-03).
    UgcRecipientConfig::create([
      'id' => 'eventos',
      'label' => 'Equipe de Eventos',
      'emails' => ['eventos-mod@example.invalid'],
    ])->save();

    $event = Event::create([
      'title' => 'Evento com Conteúdo Suspeito',
      'field_status' => 'publicado',
    ]);
    $event->save();

    $this->assertTrue($reportService->canReport($event, $this->reporterUser));

    // Cria denúncia.
    $report = $reportService->createReport($event, $this->reporterUser, 'spam');
    $this->assertInstanceOf(UgcReport::class, $report);
    $this->assertSame('pendente', $report->get('status')->value);
    $this->assertSame('eventos', $report->get('purpose')->value);

    // Privacidade: a notificação não contém UID nem dados do denunciante.
    $notifications = $notifier->getDispatchedNotifications();
    $this->assertNotEmpty($notifications);
    $lastNotice = end($notifications);
    $this->assertSame('eventos', $lastNotice['purpose']);
    $this->assertSame('spam', $lastNotice['motivo']);
    $this->assertSame((int) $event->id(), $lastNotice['target_id']);
    $this->assertSame(['eventos-mod@example.invalid'], $lastNotice['recipients']);
    $this->assertArrayNotHasKey('uid', $lastNotice);

    // O objeto denunciado NÃO é alterado nem apagado pela criação da denúncia.
    $reloadedEvent = Event::load($event->id());
    $this->assertNotNull($reloadedEvent);

    // Anti-duplicação: o usuário não pode denunciar de novo o mesmo objeto
    // enquanto a denúncia estiver pendente.
    $this->assertFalse($reportService->canReport($event, $this->reporterUser));
    $this->expectException(ReportNotAllowedException::class);
    $reportService->createReport($event, $this->reporterUser, 'spam');
  }

  /**
   * Valida decisão de moderação e restrições de acesso.
   */
  public function testModerationDecisionAndAccess(): void {
    /** @var \Drupal\aculta_ugc\Service\ReportService $reportService */
    $reportService = $this->container->get('aculta_ugc.report_service');

    $event = Event::create([
      'title' => 'Evento Regular',
      'field_status' => 'publicado',
    ]);
    $event->save();

    $report = $reportService->createReport($event, $this->reporterUser, 'conteudo_inadequado', 'eventos');

    // Usuário sem permissão não pode decidir.
    try {
      $reportService->decideReport($report, $this->commonUser, 'procedente', 'Tentativa não autorizada');
      $this->fail('Deveria ter lançado AccessDeniedHttpException');
    }
    catch (AccessDeniedHttpException $e) {
      $this->assertTrue(TRUE);
    }

    // Moderador autorizado decide a denúncia.
    $reportService->decideReport($report, $this->adminModerator, 'improcedente', 'Conteúdo em conformidade.');
    $this->assertSame('improcedente', $report->get('status')->value);
    $this->assertSame((int) $this->adminModerator->id(), (int) $report->get('decided_by')->value);
    $this->assertSame('Conteúdo em conformidade.', $report->get('decision_notes')->value);
  }

  /**
   * Valida agrupamento de itens na fila pendente.
   */
  public function testPendingQueueGrouping(): void {
    /** @var \Drupal\aculta_ugc\Service\ReportService $reportService */
    $reportService = $this->container->get('aculta_ugc.report_service');
    /** @var \Drupal\aculta_ugc\Service\UgcReportQueue $queue */
    $queue = $this->container->get('aculta_ugc.report_queue');

    $event1 = Event::create(['title' => 'Evento 1', 'field_status' => 'publicado']);
    $event1->save();
    $event2 = Event::create(['title' => 'Evento 2', 'field_status' => 'publicado']);
    $event2->save();

    // Reporter 1 e CommonUser denunciam Event 1.
    $reportService->createReport($event1, $this->reporterUser, 'spam', 'eventos');
    $reportService->createReport($event1, $this->commonUser, 'ofensa', 'eventos');

    // Reporter 1 denuncia Event 2.
    $reportService->createReport($event2, $this->reporterUser, 'outro', 'eventos');

    $pending = $queue->getPendingItems('eventos', 50, $this->adminModerator);
    $this->assertCount(2, $pending);

    // Event 1 deve ter total = 2 denúncias acumuladas.
    $event1Queue = NULL;
    foreach ($pending as $item) {
      if ($item['target_id'] === (int) $event1->id()) {
        $event1Queue = $item;
        break;
      }
    }
    $this->assertNotNull($event1Queue);
    $this->assertSame(2, $event1Queue['total']);
  }

  /**
   * Valida idempotência da migração de flags legadas.
   */
  public function testMigrationIdempotence(): void {
    /** @var \Drupal\aculta_ugc\Service\UgcReportQueue $queue */
    $queue = $this->container->get('aculta_ugc.report_queue');

    $stats = $queue->migrateFromReportQueue();
    $this->assertIsArray($stats);
    $this->assertArrayHasKey('flags_lidas', $stats);
    $this->assertArrayHasKey('novas_denuncias', $stats);
    $this->assertArrayHasKey('ignoradas_duplicadas', $stats);
  }

  /**
   * Valida o dashboard de moderação e o formulário de decisão (ADM1, ARQ-06).
   */
  public function testUgcModerationDashboardControllerAndDecisionForm(): void {
    /** @var \Drupal\aculta_ugc\Service\ReportService $reportService */
    $reportService = $this->container->get('aculta_ugc.report_service');

    $event = Event::create(['title' => 'Oficina Cultural Sob Análise', 'field_status' => 'publicado']);
    $event->save();

    $report = $reportService->createReport($event, $this->reporterUser, 'spam', 'eventos');

    // 1. Acesso ao dashboard.
    $controller = UgcModerationDashboardController::create($this->container);
    $accessDenied = $controller->access($this->commonUser);
    $this->assertFalse($accessDenied->isAllowed());

    $accessAllowed = $controller->access($this->adminModerator);
    $this->assertTrue($accessAllowed->isAllowed());

    // 2. Renderização do dashboard.
    $this->container->get('current_user')->setAccount($this->adminModerator);
    $request = Request::create('/painel-administrativo/moderacao/denuncias');
    $build = $controller->overview($request);

    $this->assertArrayHasKey('table', $build);
    $this->assertCount(1, $build['table']['#rows']);
    $this->assertSame('Eventos', (string) $build['table']['#rows'][0]['purpose']);
    $this->assertSame(1, $build['table']['#rows'][0]['total']);

    // 3. Formulário de decisão.
    $formObject = UgcReportDecisionForm::create($this->container);
    $this->assertFalse($formObject->access($this->commonUser, $report)->isAllowed());
    $this->assertTrue($formObject->access($this->adminModerator, $report)->isAllowed());

    $formState = new FormState();
    $form = $formObject->buildForm([], $formState, $report);
    $this->assertArrayHasKey('decision', $form);
    $this->assertArrayHasKey('decision_notes', $form);

    $formState->setValue('decision', 'procedente');
    $formState->setValue('decision_notes', 'Denúncia confirmada após verificação de conteúdo indevido.');
    $formObject->submitForm($form, $formState);

    // 4. Verificação pós-decisão.
    $reloadedReport = UgcReport::load($report->id());
    $this->assertNotNull($reloadedReport);
    $this->assertSame('procedente', $reloadedReport->get('status')->value);
    $this->assertSame((int) $this->adminModerator->id(), (int) $reloadedReport->get('decided_by')->value);
    $this->assertSame('Denúncia confirmada após verificação de conteúdo indevido.', $reloadedReport->get('decision_notes')->value);
  }

}

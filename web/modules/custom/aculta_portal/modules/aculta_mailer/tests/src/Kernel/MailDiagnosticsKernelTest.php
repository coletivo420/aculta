<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Controller\MailDashboardController;
use Drupal\aculta_mailer\Diagnostics\MailDiagnostics;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Diagnóstico: padrão e contingência, fila, último envio e falha por transporte; rota e página.
 */
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailDiagnosticsKernelTest extends KernelTestBase {

  /**
   * Módulos do teste (o módulo de transporte da camada de envio E-Mails ACULTA não é habilitado aqui).
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user', 'aculta_mailer'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_mail_log');
  }

  /**
   * O relatório traz padrão, contingência e fila sem transporte disponível, e o último envio aceito por transporte.
   */
  public function testReportAndLastEvents(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_mail_log');
    $storage->create([
      'correlation_id' => 'a0000000-0000-4000-8000-000000000010',
      'type' => 'account_created',
      'recipient_hash' => str_repeat('c', 64),
      'status' => 'sent',
      'transport' => 'smtp',
      'sent' => 1700000000,
      'created' => 1700000000,
    ])->save();

    $diagnostics = $this->container->get(MailDiagnostics::class);
    $report = $diagnostics->report();

    $this->assertSame(['primary' => 'smtp', 'fallback' => 'smtp2go'], $report['failover']);
    $this->assertSame([], $report['transports']);
    $this->assertSame(0, $report['queue_items']);
    $this->assertSame(1700000000, $diagnostics->lastEventsByTransport(['smtp'])['smtp']['last_sent']);
    $this->assertNull($diagnostics->lastEventsByTransport(['smtp'])['smtp']['last_failure']);
  }

  /**
   * A rota responde com a permissão do módulo e a página monta sem transportes disponíveis.
   */
  public function testRouteAndPageRender(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_mailer.diagnostics');
    $this->assertSame('administer aculta_mailer', $route->getRequirement('_permission'));
    $page = MailDashboardController::create($this->container)->diagnostics();
    $this->assertArrayHasKey('transports', $page);
  }

}

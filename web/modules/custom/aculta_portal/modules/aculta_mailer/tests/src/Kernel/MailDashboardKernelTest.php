<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Controller\MailDashboardController;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Painel de envios: contagens por período, estado, tipo e transporte; rota e página.
 */
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailDashboardKernelTest extends KernelTestBase {

  /**
   * Módulos do teste.
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
   * Conta por período, estado, tipo e transporte, e ignora registros antigos.
   */
  public function testSummaryCountsByStatusTypeAndTransport(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_mail_log');
    $agora = time();
    $this->log($storage, 'a0000000-0000-4000-8000-000000000001', 'account_created', 'sent', 'default', $agora);
    $this->log($storage, 'a0000000-0000-4000-8000-000000000002', 'account_created', 'sent', 'default', $agora);
    $this->log($storage, 'a0000000-0000-4000-8000-000000000003', 'achievement_earned', 'failed', 'default', $agora);
    $this->log($storage, 'a0000000-0000-4000-8000-000000000004', 'achievement_earned', 'sent', 'smtp2go', $agora - 90 * 86400);

    $resumo = $this->container->get('aculta_mailer.dashboard')->summary($agora - 86400);

    $this->assertSame(3, $resumo['total']);
    $this->assertSame(2, $resumo['by_status']['sent']);
    $this->assertSame(1, $resumo['by_status']['failed']);
    $this->assertSame(0, $resumo['by_status']['deferred']);
    $this->assertSame(2, $resumo['by_type']['account_created']);
    $this->assertSame(1, $resumo['by_type']['achievement_earned']);
    $this->assertSame(3, array_sum($resumo['by_transport']));
  }

  /**
   * A rota existe, exige a permissão do módulo e a página monta com os três períodos.
   */
  public function testRouteAndPageRender(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_mailer.dashboard');
    $this->assertSame('administer aculta_mailer', $route->getRequirement('_permission'));

    $page = MailDashboardController::create($this->container)->overview();
    $this->assertArrayHasKey('period_1', $page);
    $this->assertArrayHasKey('period_7', $page);
    $this->assertArrayHasKey('period_30', $page);

    // Gráfico por estado: barras nativas com o valor em texto (acessível sem cor).
    $html = (string) $this->container->get('renderer')->renderInIsolation($page['period_1']['status_chart']);
    $this->assertStringContainsString('<progress', $html);
    $this->assertSame(count(\Drupal\aculta_mailer\Message\MailStatus::cases()), substr_count($html, '<progress'), 'Uma barra por estado.');
  }

  /**
   * O relatório lista as falhas com o texto completo e a rota do relatório responde.
   */
  public function testFailureReportShowsFullError(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_mail_log');
    $storage->create([
      'correlation_id' => 'a0000000-0000-4000-8000-000000000009',
      'type' => 'account_created',
      'recipient_hash' => str_repeat('b', 64),
      'status' => 'failed',
      'transport' => 'smtp',
      'error_code' => 'smtp_550',
      'error_message' => 'Expected response code "250" but got code "550".',
      'created' => time(),
    ])->save();
    $dashboard = $this->container->get('aculta_mailer.dashboard');
    $rows = $dashboard->recentFailures(time() - 86400);
    $this->assertCount(1, $rows);
    $this->assertSame('Expected response code "250" but got code "550".', $rows[0]['error_message']);
    $this->assertSame(['smtp_550' => 1], $dashboard->summary(time() - 86400)['by_error']);

    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_mailer.failures');
    $this->assertSame('administer aculta_mailer', $route->getRequirement('_permission'));
    $page = MailDashboardController::create($this->container)->failures();
    $this->assertArrayHasKey('table', $page);
  }

  /**
   * Cria um registro de envio para o teste.
   */
  private function log($storage, string $correlation, string $type, string $status, string $transport, int $created): void {
    $storage->create([
      'correlation_id' => $correlation,
      'type' => $type,
      'recipient_hash' => str_repeat('a', 64),
      'status' => $status,
      'transport' => $transport,
      'created' => $created,
    ])->save();
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_statistics\Email\EmailFunnel;
use Drupal\aculta_statistics\Reconciliation\EmailReconciler;
use Drupal\aculta_statistics\Storage\DailyAggregateStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Conciliação do `aculta_mail_log` com os contadores, sem contar duas vezes.
 */
#[CoversClass(EmailReconciler::class)]
#[Group('aculta_statistics')]
#[RunTestsInSeparateProcesses]
final class EmailReconciliationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'aculta_mailer', 'aculta_statistics'];

  /**
   * Dia UTC fixo do teste (número de dias desde 1970-01-01).
   */
  private const DAY = 20000;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_mail_log');
    $this->installSchema('aculta_statistics', [DailyAggregateStorage::TABLE]);
  }

  /**
   * Os envios do dia são contados por estado; um envio de outro dia não entra.
   */
  public function testDayIsCountedByStateAndAcceptance(): void {
    $this->log('aviso', 'delivered', 'smtp', TRUE, self::DAY * 86400 + 100);
    $this->log('aviso', 'bounced', 'brevo', TRUE, self::DAY * 86400 + 200);
    $this->log('aviso', 'failed', 'smtp', FALSE, self::DAY * 86400 + 300, 'provedor_timeout');
    $this->log('aviso', 'delivered', 'smtp', TRUE, (self::DAY + 1) * 86400);

    $this->container->get(EmailReconciler::class)->reconcile(self::DAY, self::DAY);

    $storage = $this->container->get('aculta_statistics.storage');
    $day = self::DAY;
    $this->assertSame(['aviso' => 3], $this->dimensionTotals($storage, EmailFunnel::METRIC_QUEUED, $day));
    $this->assertSame(['brevo' => 1, 'smtp' => 1], $this->dimensionTotals($storage, EmailFunnel::METRIC_ACCEPTED, $day));
    $this->assertSame(['smtp' => 1], $this->dimensionTotals($storage, EmailFunnel::METRIC_DELIVERED, $day));
    $this->assertSame(['brevo' => 1], $this->dimensionTotals($storage, EmailFunnel::METRIC_BOUNCED, $day));
    $this->assertSame(['smtp|provedor_timeout' => 1], $this->dimensionTotals($storage, EmailFunnel::METRIC_FAILED, $day));
  }

  /**
   * Rodar de novo com o mesmo registro não soma: o total continua igual.
   */
  public function testReconcileIsIdempotent(): void {
    $this->log('aviso', 'delivered', 'smtp', TRUE, self::DAY * 86400 + 100);
    $reconciler = $this->container->get(EmailReconciler::class);

    $reconciler->reconcile(self::DAY, self::DAY);
    $reconciler->reconcile(self::DAY, self::DAY);

    $this->assertSame(1, $this->container->get('aculta_statistics.storage')->total(EmailFunnel::METRIC_DELIVERED, self::DAY, self::DAY));
  }

  /**
   * Quando a entrega chega depois, o recálculo move o envio de aceito para entregue sem duplicar.
   */
  public function testLateDeliveryMovesFromAcceptedWithoutDoubleCount(): void {
    $log = $this->log('aviso', 'sent', 'brevo', TRUE, self::DAY * 86400 + 100);
    $reconciler = $this->container->get(EmailReconciler::class);
    $storage = $this->container->get('aculta_statistics.storage');
    $reconciler->reconcile(self::DAY, self::DAY);
    $this->assertSame(0, $storage->total(EmailFunnel::METRIC_DELIVERED, self::DAY, self::DAY));

    $log->set('status', 'delivered')->save();
    $reconciler->reconcile(self::DAY, self::DAY);

    $this->assertSame(1, $storage->total(EmailFunnel::METRIC_ACCEPTED, self::DAY, self::DAY));
    $this->assertSame(1, $storage->total(EmailFunnel::METRIC_DELIVERED, self::DAY, self::DAY));
  }

  /**
   * Totais de uma métrica num dia, por dimensão.
   *
   * @return array<string, int>
   *   Dimensão e total, ordenados pela dimensão.
   */
  private function dimensionTotals(object $storage, string $metric, int $day): array {
    $totals = [];
    foreach ($storage->dimensions($metric, $day) as $dimension) {
      $totals[$dimension] = $storage->total($metric, $day, $day, $dimension);
    }
    return $totals;
  }

  /**
   * Cria um registro de envio com a data de solicitação informada.
   */
  private function log(string $type, string $status, string $transport, bool $accepted, int $created, string $errorCode = ''): object {
    $log = $this->container->get('entity_type.manager')->getStorage('aculta_mail_log')->create([
      'correlation_id' => sprintf('b%035d', $created),
      'type' => $type,
      'recipient_hash' => str_repeat('a', 64),
      'status' => $status,
      'transport' => $transport,
      'error_code' => $errorCode,
      'attempts' => 1,
      'sent' => $accepted ? $created : NULL,
      'created' => $created,
    ]);
    $log->save();
    return $log;
  }

}

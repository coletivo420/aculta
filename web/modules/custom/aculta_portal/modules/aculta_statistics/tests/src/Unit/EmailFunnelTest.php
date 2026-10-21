<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Email\EmailFunnel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Funil de e-mails: aceito, entregue, devolvido e falho, por dimensão segura.
 */
#[CoversClass(EmailFunnel::class)]
#[Group('aculta_statistics')]
final class EmailFunnelTest extends TestCase {

  /**
   * Aceito depende do campo `sent`; entregue não equivale a aceito.
   */
  public function testAcceptedIsNotDelivered(): void {
    $counts = EmailFunnel::count([
      $this->row(['status' => 'sent', 'accepted' => TRUE]),
      $this->row(['status' => 'delivered', 'accepted' => TRUE]),
      $this->row(['status' => 'failed', 'accepted' => FALSE]),
    ]);

    $this->assertSame(['smtp' => 2], $counts[EmailFunnel::METRIC_ACCEPTED]);
    $this->assertSame(['smtp' => 1], $counts[EmailFunnel::METRIC_DELIVERED]);
    $this->assertSame([], $counts[EmailFunnel::METRIC_BOUNCED]);
  }

  /**
   * Devolução entra em `bounced` por transporte; falha entra por transporte e código.
   */
  public function testBouncedAndFailedUseTransportAndCode(): void {
    $counts = EmailFunnel::count([
      $this->row(['status' => 'bounced', 'transport' => 'brevo', 'accepted' => TRUE]),
      $this->row(['status' => 'failed', 'transport' => 'smtp', 'error_code' => 'provedor_timeout']),
      $this->row(['status' => 'failed', 'transport' => 'smtp', 'error_code' => '']),
    ]);

    $this->assertSame(['brevo' => 1], $counts[EmailFunnel::METRIC_BOUNCED]);
    $this->assertSame(['smtp|provedor_timeout' => 1, 'smtp|nao_informado' => 1], $counts[EmailFunnel::METRIC_FAILED]);
  }

  /**
   * Solicitados contam por tipo, inclusive os que nunca foram aceitos.
   */
  public function testQueuedCountsEveryRowByType(): void {
    $counts = EmailFunnel::count([
      $this->row(['type' => 'link_unico', 'status' => 'queued', 'accepted' => FALSE]),
      $this->row(['type' => 'link_unico', 'status' => 'cancelled', 'accepted' => FALSE]),
      $this->row(['type' => 'resumo', 'status' => 'sent', 'accepted' => TRUE]),
    ]);

    $this->assertSame(['link_unico' => 2, 'resumo' => 1], $counts[EmailFunnel::METRIC_QUEUED]);
    $this->assertSame(['smtp' => 1], $counts[EmailFunnel::METRIC_ACCEPTED]);
  }

  /**
   * Dimensão nunca tem `@` nem passa do limite; valor vazio vira um rótulo fixo.
   */
  public function testDimensionsAreSafeAndBounded(): void {
    $counts = EmailFunnel::count([
      $this->row(['type' => 'pessoa@example.org', 'status' => 'queued', 'accepted' => FALSE]),
      $this->row(['type' => str_repeat('a', 300), 'status' => 'queued', 'accepted' => FALSE]),
      $this->row(['type' => '   ', 'status' => 'queued', 'accepted' => FALSE]),
    ]);

    $dimensions = array_keys($counts[EmailFunnel::METRIC_QUEUED]);
    $this->assertContains('pessoaexample.org', $dimensions);
    $this->assertContains(str_repeat('a', 128), $dimensions);
    $this->assertContains('nao_informado', $dimensions);
    foreach ($dimensions as $dimension) {
      $this->assertLessThanOrEqual(128, strlen((string) $dimension));
      $this->assertStringNotContainsString('@', (string) $dimension);
    }
  }

  /**
   * Sem linhas, todas as métricas existem e vêm vazias (base para zerar o dia).
   */
  public function testEmptyDayReturnsEveryMetricEmpty(): void {
    $counts = EmailFunnel::count([]);

    $this->assertSame(EmailFunnel::METRICS, array_keys($counts));
    foreach ($counts as $dimensions) {
      $this->assertSame([], $dimensions);
    }
  }

  /**
   * Linha padrão de um envio enviado pelo SMTP, sobrescrita pelos campos informados.
   *
   * @param array<string, mixed> $overrides
   *   Campos a trocar.
   *
   * @return array{type: string, status: string, transport: string, error_code: string, accepted: bool}
   *   Linha no formato de EmailFunnel::count().
   */
  private function row(array $overrides): array {
    return $overrides + [
      'type' => 'aviso',
      'status' => 'sent',
      'transport' => 'smtp',
      'error_code' => '',
      'accepted' => FALSE,
    ];
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Transições de status: sent não é delivered, e estados finais não avançam.
 */
#[CoversClass(MailStatus::class)]
#[Group('aculta_mailer')]
final class MailStatusTest extends UnitTestCase {

  /**
   * Casos de transição, com o resultado esperado.
   *
   * @return array
   *   Mapa de casos nomeados: origem, destino e resultado esperado.
   */
  public static function transitionProvider(): array {
    return [
      'fila para processamento' => [MailStatus::Queued, MailStatus::Processing, TRUE],
      'processamento aceito é sent, não delivered' => [MailStatus::Processing, MailStatus::Sent, TRUE],
      'processamento não pula para delivered' => [MailStatus::Processing, MailStatus::Delivered, FALSE],
      'sent para delivered' => [MailStatus::Sent, MailStatus::Delivered, TRUE],
      'sent para bounced' => [MailStatus::Sent, MailStatus::Bounced, TRUE],
      'deferred volta à fila para retry' => [MailStatus::Deferred, MailStatus::Queued, TRUE],
      'bounced é final' => [MailStatus::Bounced, MailStatus::Queued, FALSE],
      'failed é final' => [MailStatus::Failed, MailStatus::Queued, FALSE],
      'cancelled é final' => [MailStatus::Cancelled, MailStatus::Processing, FALSE],
      'delivered aceita reclamação posterior' => [MailStatus::Delivered, MailStatus::Complained, TRUE],
    ];
  }

  /**
   * Valida cada transição permitida e recusada.
   */
  #[DataProvider('transitionProvider')]
  public function testTransitions(MailStatus $from, MailStatus $to, bool $allowed): void {
    $this->assertSame($allowed, $from->canTransitionTo($to));
  }

  /**
   * Confirma quais estados são finais.
   */
  public function testTerminalStates(): void {
    foreach ([MailStatus::Bounced, MailStatus::Complained, MailStatus::Failed, MailStatus::Cancelled] as $status) {
      $this->assertTrue($status->isTerminal(), $status->value);
    }
    $this->assertFalse(MailStatus::Queued->isTerminal());
    $this->assertFalse(MailStatus::Delivered->isTerminal());
  }

}

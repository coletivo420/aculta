<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Delivery\RetrySchedule;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Retry só para erro temporário, com atrasos crescentes e limite de tentativas.
 */
#[CoversClass(RetrySchedule::class)]
#[Group('aculta_mailer')]
final class RetryScheduleTest extends UnitTestCase {

  /**
   * Atrasos por tentativa e limites da política.
   *
   * @return array
   *   Mapa de casos nomeados: tentativa e atraso esperado.
   */
  public static function delayProvider(): array {
    return [
      'segunda tentativa após 1 min' => [2, 60],
      'terceira após 5 min' => [3, 300],
      'quarta após 30 min' => [4, 1800],
      'quinta após 2 h' => [5, 7200],
      'sexta não existe' => [6, NULL],
      'primeira é imediata, não é retry' => [1, NULL],
    ];
  }

  /**
   * Confere o atraso de cada tentativa temporária.
   */
  #[DataProvider('delayProvider')]
  public function testTemporaryErrorDelays(int $attempt, ?int $expected): void {
    $this->assertSame($expected, (new RetrySchedule())->delayBeforeAttempt($attempt, FALSE));
  }

  /**
   * Erro permanente nunca gera nova tentativa.
   */
  public function testPermanentErrorNeverRetries(): void {
    $schedule = new RetrySchedule();
    for ($attempt = 2; $attempt <= RetrySchedule::MAX_ATTEMPTS; $attempt++) {
      $this->assertNull($schedule->delayBeforeAttempt($attempt, TRUE), 'tentativa ' . $attempt);
    }
  }

}

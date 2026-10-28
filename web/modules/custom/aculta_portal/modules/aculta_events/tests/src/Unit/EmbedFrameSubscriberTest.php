<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit;

use Drupal\aculta_events\EventSubscriber\EmbedFrameSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Política de incorporação: só os hosts da ACULTA, sem curinga.
 */
#[Group('aculta_events')]
class EmbedFrameSubscriberTest extends UnitTestCase {

  /**
   * Os hosts informados viram origens https, sem repetição e sem curinga.
   */
  public function testPoliticaListaSoOsHostsDaACULTA(): void {
    $politica = EmbedFrameSubscriber::politica(['eventos.exemplo.invalid', 'loja.exemplo.invalid', 'eventos.exemplo.invalid']);
    $this->assertSame(
      "frame-ancestors 'self' https://eventos.exemplo.invalid https://loja.exemplo.invalid",
      $politica,
    );
    $this->assertStringNotContainsString('*', $politica);
  }

}

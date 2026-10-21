<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Connector\Ga4\NullGa4ReportClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Cliente GA4 desligado: nunca faz pedido e sempre responde que está desligado.
 */
#[CoversClass(NullGa4ReportClient::class)]
#[Group('aculta_statistics')]
final class NullGa4ReportClientTest extends TestCase {

  /**
   * Desligado e sem relatório.
   */
  public function testNeverQueriesGoogle(): void {
    $client = new NullGa4ReportClient();

    $this->assertFalse($client->isEnabled());
    $this->assertNull($client->report(19990, 20000));
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit\Quote;

use Drupal\aculta_commerce_melhorenvio\Quote\QuoteResponseParser;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Leitura da cotação com uma resposta real do sandbox (tests/fixtures/quote-sandbox.json).
 */
#[Group('aculta_commerce_melhorenvio')]
final class QuoteResponseParserTest extends UnitTestCase {

  /**
   * Resposta gravada do sandbox: PAC, SEDEX e Mini Envios com erro de limite de seguro.
   *
   * @return array<mixed>
   *   JSON decodificado.
   */
  private static function fixture(): array {
    return json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/fixtures/quote-sandbox.json'), TRUE);
  }

  /**
   * Serviços disponíveis viram opções; o serviço com erro fica de fora.
   */
  public function testSandboxResponseSkipsServicesWithError(): void {
    $options = (new QuoteResponseParser())->parse(self::fixture());
    self::assertSame(['1', '2'], array_map(static fn($o) => $o->serviceId, $options));
    self::assertSame('PAC', $options[0]->name);
    self::assertSame('Correios', $options[0]->company);
    self::assertSame('28.33', $options[0]->amount);
    self::assertSame(7, $options[0]->deliveryDays);
    self::assertSame('PAC (Correios)', $options[0]->label());
  }

  /**
   * O preço `custom_price` tem prioridade sobre `price`; valores inválidos e itens sem id são ignorados.
   */
  public function testCustomPriceAndInvalidItems(): void {
    $options = (new QuoteResponseParser())->parse([
      ['id' => 9, 'name' => 'X', 'price' => '10.00', 'custom_price' => '8.5', 'company' => ['name' => 'T']],
      ['id' => 10, 'name' => 'Sem preço', 'company' => ['name' => 'T']],
      ['id' => 11, 'name' => 'Negativo', 'price' => '-1'],
      ['name' => 'Sem id', 'price' => '1.00'],
      'lixo',
    ]);
    self::assertCount(1, $options);
    self::assertSame('8.50', $options[0]->amount);
    self::assertNull($options[0]->deliveryDays);
  }

}

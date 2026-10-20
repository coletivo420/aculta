<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Log\MailLogSettings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Registro de envios: padrão sem valor gravado, e prazo de retenção só dentro da lista permitida.
 */
#[CoversClass(MailLogSettings::class)]
#[Group('aculta_mailer')]
final class MailLogSettingsTest extends UnitTestCase {

  /**
   * Configuração com os valores informados.
   */
  private function settings(array $values): MailLogSettings {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn(string $key): mixed => $values[$key] ?? NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);
    return new MailLogSettings($factory);
  }

  /**
   * Sem valor gravado, o registro fica ligado e a retenção é de 90 dias (comportamento anterior).
   */
  public function testDefaultsKeepPreviousBehaviour(): void {
    $settings = $this->settings([]);
    $this->assertTrue($settings->enabled());
    $this->assertSame(90, $settings->retentionDays());
  }

  /**
   * Desligado só quando gravado como falso.
   */
  public function testDisabledOnlyWhenStoredFalse(): void {
    $this->assertFalse($this->settings(['enabled' => FALSE])->enabled());
    $this->assertTrue($this->settings(['enabled' => TRUE])->enabled());
  }

  /**
   * Prazo fora da lista permitida volta ao padrão.
   */
  #[DataProvider('retentionProvider')]
  public function testRetentionOnlyAllowedValues(mixed $stored, int $expected): void {
    $this->assertSame($expected, $this->settings(['retention_days' => $stored])->retentionDays());
  }

  /**
   * Casos de prazo.
   */
  public static function retentionProvider(): array {
    return [
      'um mês' => [30, 30],
      'seis meses' => [180, 180],
      'um ano' => [365, 365],
      'fora da lista' => [45, 90],
      'vazio' => [NULL, 90],
    ];
  }

}

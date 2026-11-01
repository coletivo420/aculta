<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Type\AdminStatisticsMailType;
use Drupal\Core\StringTranslation\TranslationInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Modelo de e-mail com as estatísticas da plataforma.
 */
#[CoversClass(AdminStatisticsMailType::class)]
#[Group('aculta_mailer')]
final class AdminStatisticsMailTypeTest extends TestCase {

  /**
   * Identificador e variáveis do modelo.
   */
  public function testExposesIdAndVariables(): void {
    $type = new AdminStatisticsMailType($this->translation());

    $this->assertSame('aculta_admin_statistics', $type->id());
    $this->assertSame(['periodo', 'estatisticas'], array_keys($type->variables()));
  }

  /**
   * O corpo usa as variáveis declaradas.
   */
  public function testBodyUsesDeclaredVariables(): void {
    $type = new AdminStatisticsMailType($this->translation());

    $this->assertStringContainsString('{{ periodo }}', $type->defaultBody());
    $this->assertStringContainsString('{{ estatisticas }}', $type->defaultBody());
  }

  /**
   * Tradução identidade, para o teste sem container.
   */
  private function translation(): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(static fn (string $text): object => new class($text) {
      public function __construct(private readonly string $text) {}
      public function __toString(): string {
        return $this->text;
      }
    });
    return $translation;
  }

}

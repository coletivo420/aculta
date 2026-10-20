<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_game\Unit;

use Drupal\aculta_game\Mail\AchievementEarnedMailType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Texto do aviso de conquista: assunto, corpo e identificador do tipo.
 */
#[CoversClass(AchievementEarnedMailType::class)]
#[Group('aculta_game')]
final class AchievementEarnedMailTypeTest extends UnitTestCase {

  /**
   * Assunto e corpo levam o nome da conquista e o link recebidos no contexto.
   */
  public function testSubjectAndBodyUseContext(): void {
    $type = new AchievementEarnedMailType($this->getStringTranslationStub());
    $context = ['label' => 'Primeira leitura', 'url' => 'https://example.invalid/conta'];

    $this->assertSame('achievement_earned', $type->id());
    $this->assertSame('Você conquistou: Primeira leitura', $type->subject($context));
    $this->assertStringContainsString('“Primeira leitura”', $type->body($context));
    $this->assertStringContainsString('https://example.invalid/conta', $type->body($context));
  }

}

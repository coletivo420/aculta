<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_game\Unit;

use Drupal\aculta_game\Mail\AchievementEarnedMailType;
use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Texto padrão do aviso de conquista: placeholders declarados e renderização com o contexto.
 */
#[CoversClass(AchievementEarnedMailType::class)]
#[Group('aculta_game')]
final class AchievementEarnedMailTypeTest extends UnitTestCase {

  /**
   * O texto padrão só usa variáveis declaradas e sai com o nome e o link no contexto.
   */
  public function testDefaultTextUsesDeclaredVariables(): void {
    $type = new AchievementEarnedMailType($this->getStringTranslationStub());
    $allowed = array_keys($type->variables());
    $context = ['label' => 'Primeira leitura', 'url' => 'https://example.invalid/conta'];

    $this->assertSame('achievement_earned', $type->id());
    $this->assertSame([], MailTemplateRenderer::unknownPlaceholders($type->defaultSubject(), $allowed));
    $this->assertSame([], MailTemplateRenderer::unknownPlaceholders($type->defaultBody(), $allowed));
    $this->assertSame('Você conquistou: Primeira leitura', MailTemplateRenderer::fill($type->defaultSubject(), $context));
    $body = MailTemplateRenderer::fill($type->defaultBody(), $context);
    $this->assertStringContainsString('“Primeira leitura”', $body);
    $this->assertStringContainsString('https://example.invalid/conta', $body);
  }

}

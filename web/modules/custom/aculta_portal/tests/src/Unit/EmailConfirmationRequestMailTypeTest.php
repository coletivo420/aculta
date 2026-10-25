<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\aculta_portal\Email\EmailConfirmationRequestMailType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tipo do pedido de confirmação de e-mail: variáveis declaradas e texto padrão só com elas.
 */
#[CoversClass(EmailConfirmationRequestMailType::class)]
#[Group('aculta_portal')]
final class EmailConfirmationRequestMailTypeTest extends UnitTestCase {

  /**
   * O texto padrão usa só as variáveis declaradas e preserva o link de resposta.
   */
  public function testDefaultTextUsesOnlyDeclaredVariables(): void {
    $type = new EmailConfirmationRequestMailType($this->getStringTranslationStub());
    $allowed = array_keys($type->variables());

    $this->assertSame('email_confirmation_request', $type->id());
    $this->assertSame([], MailTemplateRenderer::unknownPlaceholders($type->defaultSubject(), $allowed));
    $this->assertSame([], MailTemplateRenderer::unknownPlaceholders($type->defaultBody(), $allowed));
    $body = MailTemplateRenderer::fill($type->defaultBody(), [
      'email' => 'pessoa@example.invalid',
      'confirm_url' => 'https://example.invalid/aculta-mail-confirmer/responder/x/hash',
    ]);
    $this->assertStringContainsString('pessoa@example.invalid', $body);
    $this->assertStringContainsString('https://example.invalid/aculta-mail-confirmer/responder/x/hash', $body);
  }

}

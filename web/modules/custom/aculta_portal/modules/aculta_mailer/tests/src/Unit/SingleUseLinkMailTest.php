<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Log\SingleUseLinkMail;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * E-mails com link de uso único têm prazo de 24 horas para o conteúdo guardado; os demais não.
 */
#[Group('aculta_mailer')]
final class SingleUseLinkMailTest extends UnitTestCase {

  /**
   * Recuperação de senha, confirmação e acesso de cadastro entram na regra.
   */
  public function testLinkMailsApply(): void {
    $this->assertTrue(SingleUseLinkMail::applies('user', 'password_reset'));
    $this->assertTrue(SingleUseLinkMail::applies('aculta_mailer', 'email_confirmation_request'));
    $this->assertTrue(SingleUseLinkMail::applies('aculta_mailer', 'registration_existing_mail'));
    $this->assertTrue(SingleUseLinkMail::applies('aculta_mail_confirmer', 'confirmation_request'));
  }

  /**
   * Alerta de falha e resumo não têm link de uso único.
   */
  public function testOtherMailsDoNotApply(): void {
    $this->assertFalse(SingleUseLinkMail::applies('aculta_mailer', 'aculta_admin_alert'));
    $this->assertFalse(SingleUseLinkMail::applies('aculta_mailer', 'aculta_weekly_summary'));
    // Mesma chave em outro módulo não conta.
    $this->assertFalse(SingleUseLinkMail::applies('other', 'password_reset'));
  }

  /**
   * O conteúdo guardado não tem URL: texto e atributo href saem, e o restante do texto fica.
   */
  public function testWithoutLinksRemovesUrls(): void {
    $text = 'Confirme em https://conta.example.invalid/confirmar?token=abc123 em breve.';
    $this->assertSame('Confirme em [link removido] em breve.', SingleUseLinkMail::withoutLinks($text));
    $html = '<a href="https://conta.example.invalid/redefinir?t=xyz">Redefinir</a>';
    $this->assertSame('<a href="[link removido]">Redefinir</a>', SingleUseLinkMail::withoutLinks($html));
    $this->assertStringNotContainsString('token=', SingleUseLinkMail::withoutLinks($text));
  }

  /**
   * O prazo é de 24 horas.
   */
  public function testTtlIsOneDay(): void {
    $this->assertSame(86400, SingleUseLinkMail::TTL);
  }

}

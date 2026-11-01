<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Mail;

use Drupal\aculta_portal\Mail\TestMailRedirect;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Redirecionamento de e-mails do ambiente de testes.
 */
#[Group('aculta_portal')]
final class TestMailRedirectTest extends UnitTestCase {

  public function testSemConfiguracaoNaoAlteraAMensagem(): void {
    $mensagem = ['to' => 'pessoa@example.com', 'subject' => 'Pedido', 'headers' => ['Cc' => 'x@example.com']];
    (new TestMailRedirect(new Settings([])))->aplicar($mensagem);
    $this->assertSame('pessoa@example.com', $mensagem['to']);
    $this->assertSame('Pedido', $mensagem['subject']);
    $this->assertArrayHasKey('Cc', $mensagem['headers']);
  }

  public function testComConfiguracaoRedirecionaERegistraOOriginal(): void {
    $mensagem = ['to' => 'Teste.Comum@testuser.com', 'subject' => 'Pedido', 'headers' => ['Cc' => 'x@example.com']];
    $ajuste = new Settings(['aculta_mail_redirect' => '4e20coletivo@gmail.com', 'aculta_mail_redirect_for' => ['teste.comum@testuser.com']]);
    (new TestMailRedirect($ajuste))->aplicar($mensagem);
    $this->assertSame('4e20coletivo@gmail.com', $mensagem['to']);
    $this->assertSame('[TESTE para Teste.Comum@testuser.com] Pedido', $mensagem['subject']);
    $this->assertSame('Teste.Comum@testuser.com', $mensagem['headers']['X-Aculta-Destino-Original']);
    $this->assertArrayNotHasKey('Cc', $mensagem['headers']);
  }

  public function testOutrosDestinatariosNaoSaoRedirecionados(): void {
    $mensagem = ['to' => 'pessoa@example.com', 'subject' => 'Pedido', 'headers' => []];
    $ajuste = new Settings(['aculta_mail_redirect' => '4e20coletivo@gmail.com', 'aculta_mail_redirect_for' => ['teste.comum@testuser.com']]);
    (new TestMailRedirect($ajuste))->aplicar($mensagem);
    $this->assertSame('pessoa@example.com', $mensagem['to']);
    $this->assertArrayNotHasKey('X-Aculta-Destino-Original', $mensagem['headers']);
  }

  public function testConfiguracaoVaziaEhIgualAAusente(): void {
    $this->assertNull((new TestMailRedirect(new Settings(['aculta_mail_redirect' => '  '])))->destino());
  }

}

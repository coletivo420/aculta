<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Mail\LegacyMessageConverter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Mensagem do Core para e-mail do Symfony: remetente, destinatário, cabeçalhos e transporte escolhido.
 */
#[CoversClass(LegacyMessageConverter::class)]
#[Group('aculta_mailer')]
final class LegacyMessageConverterTest extends UnitTestCase {

  /**
   * Remetente, destinatário, assunto, corpo e Reply-To vêm da mensagem; o cabeçalho de conteúdo é ignorado.
   */
  public function testBuildsEmailFromLegacyMessage(): void {
    $email = (new LegacyMessageConverter())->toEmail([
      'from' => 'Teste <naoresponda@example.invalid>',
      'to' => 'destino@example.invalid',
      'subject' => 'Assunto',
      'body' => 'Corpo',
      'headers' => [
        'Reply-to' => 'resposta@example.invalid',
        'Content-Type' => 'text/html',
        'X-Aculta-Mail-ID' => 'abc-123',
        'X-Priority' => '3',
      ],
    ], 'padrao@example.invalid');

    $this->assertSame('naoresponda@example.invalid', $email->getFrom()[0]->getAddress());
    $this->assertSame('Teste', $email->getFrom()[0]->getName());
    $this->assertSame('destino@example.invalid', $email->getTo()[0]->getAddress());
    $this->assertSame('Assunto', $email->getSubject());
    $this->assertSame('Corpo', $email->getTextBody());
    $this->assertSame('resposta@example.invalid', $email->getReplyTo()[0]->getAddress());
    $this->assertSame('abc-123', $email->getHeaders()->getHeaderBody('X-Aculta-Mail-ID'));
    $this->assertSame('3', $email->getHeaders()->getHeaderBody('X-Priority'));
  }

  /**
   * Sem remetente na mensagem, vale o remetente do site.
   */
  public function testFallsBackToSiteSender(): void {
    $email = (new LegacyMessageConverter())->toEmail([
      'to' => 'destino@example.invalid',
      'subject' => 'x',
      'body' => 'y',
    ], 'padrao@example.invalid');
    $this->assertSame('padrao@example.invalid', $email->getFrom()[0]->getAddress());
  }

  /**
   * Envelope HTML vai como parte HTML, e anexo em memória entra como anexo.
   */
  public function testHtmlAndAttachmentsAreAdded(): void {
    $email = (new LegacyMessageConverter())->toEmail([
      'from' => 'naoresponda@example.invalid',
      'to' => 'destino@example.invalid',
      'subject' => 'x',
      'body' => 'y',
      'params' => ['attachments' => [['filecontent' => 'conteudo', 'filename' => 'a.txt', 'filemime' => 'text/plain']]],
    ], 'padrao@example.invalid', '<p>y</p>');
    $this->assertSame('<p>y</p>', $email->getHtmlBody());
    $this->assertCount(1, $email->getAttachments());
  }

  /**
   * Transporte pelo X-Transport das políticas; ausente ou desconhecido vai para o padrão.
   */
  public function testTransportIdMapsLegacyNames(): void {
    $converter = new LegacyMessageConverter();
    $this->assertSame('smtp_hostinger', $converter->transportId(['headers' => ['X-Transport' => 'smtp']]));
    $this->assertSame('brevo', $converter->transportId(['headers' => ['X-Transport' => 'brevo']]));
    $this->assertSame('smtp_hostinger', $converter->transportId(['headers' => []]));
    $this->assertSame('smtp_hostinger', $converter->transportId(['headers' => ['X-Transport' => 'desconhecido']]));
  }

}

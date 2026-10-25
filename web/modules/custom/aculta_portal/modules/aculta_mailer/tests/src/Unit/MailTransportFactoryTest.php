<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Entity\MailTransport;
use Drupal\aculta_mailer\Transport\BrevoSecretTransportFactory;
use Drupal\aculta_mailer\Transport\MailTransportFactory;
use Drupal\aculta_mailer\Transport\SecretProviderInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Transportes a partir da entidade: SMTP com credencial pelo nome, Brevo pela chave, e falha fechada sem credencial.
 */
#[CoversClass(MailTransportFactory::class)]
#[Group('aculta_mailer')]
final class MailTransportFactoryTest extends UnitTestCase {

  /**
   * Fábrica com os segredos informados (nome => valor).
   */
  private function factory(array $secrets): MailTransportFactory {
    $provider = $this->createMock(SecretProviderInterface::class);
    $provider->method('value')->willReturnCallback(static fn(string $name): ?string => $secrets[$name] ?? NULL);
    return new MailTransportFactory($provider, new BrevoSecretTransportFactory($provider));
  }

  /**
   * Entidade de transporte com os valores informados.
   */
  private function transport(array $values): MailTransport {
    return new MailTransport($values + ['id' => 'teste', 'label' => 'Teste'], MailTransport::ENTITY_TYPE);
  }

  /**
   * SMTP com usuário e credencial presentes: transporte SMTP autenticado, com verificação de certificado.
   */
  public function testSmtpWithCredentialBuildsAuthenticatedTransport(): void {
    $transport = $this->factory(['SMTP_PASSWORD' => 'segredo'])->create($this->transport([
      'plugin' => 'smtp',
      'host' => 'smtp.example.invalid',
      'port' => 465,
      'user' => 'naoresponda@example.invalid',
      'password_secret' => 'SMTP_PASSWORD',
      'verify_peer' => TRUE,
    ]));
    $this->assertInstanceOf(EsmtpTransport::class, $transport);
    $this->assertInstanceOf(SocketStream::class, $transport->getStream());
  }

  /**
   * Usuário vindo do arquivo de segredos (user_secret) e senha pelo nome: transporte autenticado.
   */
  public function testUserFromSecretBuildsAuthenticatedTransport(): void {
    $transport = $this->factory(['SMTP2GO_USERNAME' => 'usuario', 'SMTP2GO_PASSWORD' => 'segredo'])->create($this->transport([
      'plugin' => 'smtp',
      'host' => 'mail.example.invalid',
      'port' => 465,
      'user_secret' => 'SMTP2GO_USERNAME',
      'password_secret' => 'SMTP2GO_PASSWORD',
    ]));
    $this->assertInstanceOf(EsmtpTransport::class, $transport);
  }

  /**
   * Usuário informado sem credencial no arquivo de segredos: recusa, sem tentar enviar sem autenticação.
   */
  public function testMissingCredentialFailsClosed(): void {
    $this->expectException(\RuntimeException::class);
    $this->factory([])->create($this->transport([
      'plugin' => 'smtp',
      'host' => 'smtp.example.invalid',
      'port' => 465,
      'user' => 'naoresponda@example.invalid',
      'password_secret' => 'SMTP_PASSWORD',
    ]));
  }

  /**
   * Brevo usa a chave do arquivo de segredos e vira transporte de API.
   */
  public function testBrevoBuildsApiTransport(): void {
    $transport = $this->factory(['BREVO_API_KEY' => 'xkeysib-teste'])->create($this->transport(['plugin' => 'brevo']));
    $this->assertInstanceOf(TransportInterface::class, $transport);
    $this->assertStringStartsWith('brevo+api://', (string) $transport);
  }

  /**
   * Tipo desconhecido não cria transporte.
   */
  public function testUnknownPluginIsRejected(): void {
    $this->expectException(\RuntimeException::class);
    $this->factory([])->create($this->transport(['plugin' => 'sendmail']));
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Transport\BrevoSecretTransportFactory;
use Drupal\aculta_mailer\Transport\SecretProviderInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * Fábrica do transporte Brevo: chave do segredo, não da DSN; sem chave, nada é criado.
 */
#[CoversClass(BrevoSecretTransportFactory::class)]
#[Group('aculta_mailer')]
final class BrevoSecretTransportFactoryTest extends UnitTestCase {

  /**
   * Com a credencial configurada, cria o transporte da API do Brevo.
   */
  public function testCreatesBrevoApiTransportWhenSecretIsSet(): void {
    if (!class_exists(HttpClient::class)) {
      $this->markTestSkipped('Requer symfony/http-client, ainda não autorizado no Composer.');
    }
    $secrets = $this->createMock(SecretProviderInterface::class);
    $secrets->method('value')->with('BREVO_API_KEY')->willReturn('chave-de-teste');
    $factory = new BrevoSecretTransportFactory($secrets);

    $transport = $factory->create(Dsn::fromString('aculta-brevo://default'));

    $this->assertInstanceOf(BrevoApiTransport::class, $transport);
  }

  /**
   * Sem a credencial, o transporte não é criado.
   */
  public function testThrowsWhenSecretIsMissing(): void {
    $secrets = $this->createMock(SecretProviderInterface::class);
    $secrets->method('value')->willReturn(NULL);
    $factory = new BrevoSecretTransportFactory($secrets);

    $this->expectException(\RuntimeException::class);
    $factory->create(Dsn::fromString('aculta-brevo://default'));
  }

  /**
   * Outro esquema de DSN é recusado.
   */
  public function testRejectsOtherSchemes(): void {
    $factory = new BrevoSecretTransportFactory($this->createMock(SecretProviderInterface::class));

    $this->expectException(UnsupportedSchemeException::class);
    $factory->create(Dsn::fromString('smtp://smtp.example.invalid:465'));
  }

}

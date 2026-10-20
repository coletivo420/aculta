<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Message\MessageIdFactory;
use Drupal\Component\Uuid\Php;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Message-ID e correlation ID: formato RFC e host vindo de configuração.
 */
#[CoversClass(MessageIdFactory::class)]
#[Group('aculta_mailer')]
final class MessageIdFactoryTest extends UnitTestCase {

  /**
   * Gera UUID v4 em minúsculas.
   */
  public function testGeneratesLowercaseUuidV4(): void {
    $factory = new MessageIdFactory(new Php(), 'mail.example.test');
    $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
    $this->assertMatchesRegularExpression($pattern, $factory->newCorrelationId());
  }

  /**
   * Monta o Message-ID com o host recebido por configuração.
   */
  public function testBuildsMessageIdWithConfiguredHost(): void {
    $factory = new MessageIdFactory(new Php(), 'mail.example.test');
    $correlation = '0190a3f2-7c1d-4e8a-9b2f-3d4e5f6a7b8c';
    $this->assertSame('<' . $correlation . '@mail.example.test>', $factory->messageIdFor($correlation));
  }

  /**
   * Rejeita host sem domínio.
   */
  public function testRejectsHostWithoutDomain(): void {
    $this->expectException(\InvalidArgumentException::class);
    new MessageIdFactory(new Php(), 'localhost');
  }

  /**
   * Rejeita host com caracteres inválidos.
   */
  public function testRejectsHostWithInvalidCharacters(): void {
    $this->expectException(\InvalidArgumentException::class);
    new MessageIdFactory(new Php(), 'mail example.test');
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Log\MailPayloadCipher;
use Drupal\aculta_mailer\Transport\SecretProviderInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cifragem do conteúdo para reenvio: ida e volta, chave errada, valor alterado e ausência de chave válida.
 */
#[Group('aculta_mailer')]
final class MailPayloadCipherTest extends UnitTestCase {

  /**
   * Provedor com um valor de segredo.
   */
  private function secrets(?string $value): SecretProviderInterface {
    return new class($value) implements SecretProviderInterface {

      public function __construct(private readonly ?string $value) {}

      /**
       * {@inheritdoc}
       */
      public function value(string $name): ?string {
        return $name === 'MAILER_PAYLOAD_KEY' ? $this->value : NULL;
      }

    };
  }

  /**
   * Conteúdo de exemplo, com destinatário.
   */
  private function payload(): array {
    return [
      'to' => 'pessoa@example.invalid',
      'subject' => 'Assunto',
      'body' => 'Corpo',
      'html' => '<p>Corpo</p>',
      'headers' => ['X-Aculta-Mail-ID' => 'abc'],
    ];
  }

  /**
   * Com a chave certa, o conteúdo volta igual e o texto guardado não contém o endereço.
   */
  public function testRoundTripWithKeyHidesRecipient(): void {
    $cipher = new MailPayloadCipher($this->secrets(base64_encode(random_bytes(32))));
    $stored = $cipher->encrypt($this->payload());
    $this->assertIsString($stored);
    $this->assertStringNotContainsString('pessoa@example.invalid', $stored);
    $this->assertSame($this->payload(), $cipher->decrypt($stored));
  }

  /**
   * Outra chave não decifra.
   */
  public function testWrongKeyDoesNotDecrypt(): void {
    $stored = (new MailPayloadCipher($this->secrets(base64_encode(random_bytes(32)))))->encrypt($this->payload());
    $this->assertNull((new MailPayloadCipher($this->secrets(base64_encode(random_bytes(32)))))->decrypt($stored));
  }

  /**
   * Valor alterado não decifra (autenticação do Poly1305).
   */
  public function testTamperedValueDoesNotDecrypt(): void {
    $cipher = new MailPayloadCipher($this->secrets(base64_encode(random_bytes(32))));
    $stored = $cipher->encrypt($this->payload());
    $raw = base64_decode(substr($stored, 3), TRUE);
    $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";
    $this->assertNull($cipher->decrypt('v1.' . base64_encode($raw)));
  }

  /**
   * Sem chave, ou com chave de tamanho errado, nada é cifrado.
   */
  public function testNoValidKeyEncryptsNothing(): void {
    $this->assertNull((new MailPayloadCipher($this->secrets(NULL)))->encrypt($this->payload()));
    $this->assertNull((new MailPayloadCipher($this->secrets('')))->encrypt($this->payload()));
    $this->assertNull((new MailPayloadCipher($this->secrets(base64_encode('curta'))))->encrypt($this->payload()));
  }

}

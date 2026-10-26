<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit;

use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEnvironment;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioTokenCipher;
use Drupal\aculta_commerce_melhorenvio\Http\StateMelhorEnvioTokenStore;
use Drupal\aculta_portal\Secrets\SecretsManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cifra dos tokens: ida e volta, chave errada, ausente e falha fechada.
 */
#[CoversClass(MelhorEnvioTokenCipher::class)]
#[CoversClass(StateMelhorEnvioTokenStore::class)]
#[Group('aculta_commerce_melhorenvio')]
class MelhorEnvioTokenCipherTest extends UnitTestCase {

  private const KEY_ID = 'aculta_melhorenvio_token_encryption';

  private const TOKENS = [
    'access_token' => 'token-ficticio-acesso',
    'refresh_token' => 'token-ficticio-renovacao',
    'expires_at' => 1900000000,
  ];

  /**
   * Diretório temporário com o contrato de segredos de fixture. */
  private string $base;

  /**
   * Cria o diretório temporário e o contrato de segredos de fixture.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->base = sys_get_temp_dir() . '/aculta-cipher-' . bin2hex(random_bytes(6));
    mkdir($this->base . '/web', 0700, TRUE);
    mkdir($this->base . '/config', 0700, TRUE);
    file_put_contents($this->base . '/config/secrets-contract.json', json_encode([
      'variables' => [
        [
          'name' => 'ACULTA_MELHORENVIO_TOKEN_ENCRYPTION_KEY',
          'key_id' => self::KEY_ID,
          'environments' => ['production', 'test'],
          'required_in' => ['production', 'test'],
        ],
      ],
    ]));
  }

  /**
   * Remove o diretório temporário.
   */
  protected function tearDown(): void {
    @unlink($this->base . '/config/secrets-contract.json');
    @rmdir($this->base . '/config');
    @rmdir($this->base . '/web');
    @rmdir($this->base);
    parent::tearDown();
  }

  /**
   * Ida e volta: os tokens voltam iguais e o payload não contém o texto puro.
   */
  public function testRoundTripRestoresTokensAndNeverStoresPlainText(): void {
    $cipher = $this->cipher(str_repeat('ab', 32));
    $payload = $cipher->encrypt(json_encode(self::TOKENS, JSON_THROW_ON_ERROR));

    $this->assertStringNotContainsString('token-ficticio', $payload);
    $this->assertSame(json_encode(self::TOKENS, JSON_THROW_ON_ERROR), $cipher->decrypt($payload));
  }

  /**
   * Cada cifragem usa um nonce novo e continua decifrando para o mesmo texto.
   */
  public function testEncryptionUsesFreshNonceAndStillDecrypts(): void {
    $cipher = $this->cipher(str_repeat('ab', 32));
    $first = $cipher->encrypt('mesmo texto');
    $second = $cipher->encrypt('mesmo texto');

    $this->assertNotSame($first, $second);
    $this->assertSame('mesmo texto', $cipher->decrypt($first));
    $this->assertSame('mesmo texto', $cipher->decrypt($second));
  }

  /**
   * Chave diferente não autentica o payload.
   */
  public function testWrongKeyFailsAuthentication(): void {
    $payload = $this->cipher(str_repeat('ab', 32))->encrypt('segredo');

    $this->expectException(\RuntimeException::class);
    $this->cipher(str_repeat('cd', 32))->decrypt($payload);
  }

  /**
   * Sem chave, cifrar falha (nada é gravado).
   */
  public function testMissingKeyFailsClosedInBothDirections(): void {
    $cipher = $this->cipher(NULL);

    $this->expectException(\RuntimeException::class);
    $cipher->encrypt('segredo');
  }

  /**
   * Sem chave, decifrar falha mesmo para um payload válido.
   */
  public function testMissingKeyCannotDecryptAnExistingPayload(): void {
    $payload = $this->cipher(str_repeat('ab', 32))->encrypt('segredo');

    $this->expectException(\RuntimeException::class);
    $this->cipher(NULL)->decrypt($payload);
  }

  /**
   * Chave com tamanho errado é recusada antes do uso.
   */
  public function testMalformedKeyIsRejectedBeforeUse(): void {
    $this->expectException(\RuntimeException::class);
    $this->cipher('abcd')->encrypt('segredo');
  }

  /**
   * Payload adulterado não autentica.
   */
  public function testTamperedPayloadFailsAuthentication(): void {
    $cipher = $this->cipher(str_repeat('ab', 32));
    $payload = $cipher->encrypt('segredo');
    $suffix = str_ends_with($payload, 'A=') ? 'B=' : 'A=';
    $tampered = substr($payload, 0, -2) . $suffix;

    $this->expectException(\RuntimeException::class);
    $cipher->decrypt($tampered);
  }

  /**
   * Store sem chave devolve NULL, nunca texto puro.
   */
  public function testStoreReturnsNullWhenKeyIsMissingInsteadOfPlainText(): void {
    $payload = $this->cipher(str_repeat('ab', 32))->encrypt(json_encode(self::TOKENS, JSON_THROW_ON_ERROR));
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturn(['format' => 'encrypted_v1', 'payload' => $payload]);

    $store = new StateMelhorEnvioTokenStore($state, $this->cipher(NULL));

    $this->assertNull($store->load(MelhorEnvioEnvironment::Sandbox));
  }

  /**
   * Store sem chave não grava nada no State.
   */
  public function testStoreSaveFailsWithoutKeyAndWritesNothing(): void {
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->never())->method('set');

    $store = new StateMelhorEnvioTokenStore($state, $this->cipher(NULL));

    $this->expectException(\RuntimeException::class);
    $store->save(MelhorEnvioEnvironment::Sandbox, self::TOKENS);
  }

  /**
   * Malformado: apagado e contado à parte, sem exceção. O válido é cifrado.
   */
  public function testMigrationDeletesMalformedRecordAndCountsItWithoutException(): void {
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnCallback(static fn (string $key): mixed => match ($key) {
      'aculta_commerce_melhorenvio.tokens.sandbox' =>
        ['access_token' => 'token-ficticio-acesso'],
      'aculta_commerce_melhorenvio.tokens.production' => self::TOKENS,
      default => NULL,
    });
    $state->expects($this->once())->method('set')->with(
      'aculta_commerce_melhorenvio.tokens.production',
      $this->callback(static fn (array $v): bool => ($v['format'] ?? NULL) === 'encrypted_v1'),
    );

    $store = new StateMelhorEnvioTokenStore($state, $this->cipher(str_repeat('ab', 32)));

    $this->assertSame(['migrated' => 1, 'malformed' => 1], $store->migrateLegacyTokens());
  }

  /**
   * Sem a chave de cifragem a migração falha e não grava nem apaga nada.
   */
  public function testMigrationWithoutKeyFailsAndWritesNothing(): void {
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnCallback(static fn (string $key): mixed =>
      $key === 'aculta_commerce_melhorenvio.tokens.sandbox'
        ? self::TOKENS : NULL);
    $state->expects($this->never())->method('set');
    $state->expects($this->never())->method('delete');

    $store = new StateMelhorEnvioTokenStore($state, $this->cipher(NULL));

    $this->expectException(\RuntimeException::class);
    $store->migrateLegacyTokens();
  }

  /**
   * Cifra real sobre SecretsManager real, com a chave vinda de um Key simulado.
   */
  private function cipher(?string $hex): MelhorEnvioTokenCipher {
    $keys = $this->createMock(KeyRepositoryInterface::class);
    $keys->method('getKey')->willReturnCallback(function (string $id) use ($hex): ?KeyInterface {
      if ($id !== self::KEY_ID || $hex === NULL) {
        return NULL;
      }
      $key = $this->createMock(KeyInterface::class);
      $key->method('getKeyValue')->willReturn($hex);
      return $key;
    });
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    $settings = new Settings([
      'aculta_secrets_file' => $this->base . '/secrets/inexistente.env',
      'aculta_secrets_environment' => 'test',
      'aculta_secrets_storage' => 'file',
    ]);
    return new MelhorEnvioTokenCipher(new SecretsManager($keys, $settings, $factory, $this->base . '/web'));
  }

}

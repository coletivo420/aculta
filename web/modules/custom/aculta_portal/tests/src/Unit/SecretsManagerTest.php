<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Secrets\SecretsFormat;
use Drupal\aculta_portal\Secrets\SecretsManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Credenciais do ambiente: máscara, salvamento com merge e regras de armazenamento.
 */
#[\PHPUnit\Framework\Attributes\Group('aculta_portal')]
final class SecretsManagerTest extends UnitTestCase {

  private string $base;

  protected function setUp(): void {
    parent::setUp();
    $this->base = sys_get_temp_dir() . '/aculta-manager-' . bin2hex(random_bytes(6));
    mkdir($this->base . '/web', 0700, TRUE);
    mkdir($this->base . '/config', 0700, TRUE);
    mkdir($this->base . '/secrets', 0700, TRUE);
    file_put_contents($this->base . '/config/secrets-contract.json', json_encode([
      'variables' => [
        ['name' => 'GOOGLE_OAUTH_CLIENT_ID', 'key_id' => 'g_id', 'environments' => ['production', 'test'], 'required_in' => ['production', 'test']],
        ['name' => 'EXEMPLO_SENHA', 'key_id' => 'smtp_pw', 'environments' => ['test'], 'required_in' => ['test']],
        ['name' => 'TURNSTILE_KEYS_JSON', 'key_id' => 'turnstile', 'environments' => ['production'], 'required_in' => []],
        ['name' => 'BREVO_API_KEY', 'key_id' => 'brevo_api_key', 'environments' => ['production', 'test'], 'required_in' => []],
      ],
    ]));
  }

  protected function tearDown(): void {
    foreach (glob($this->base . '/secrets/*') ?: [] as $f) {
      @unlink($f);
    }
    @rmdir($this->base . '/secrets');
    @unlink($this->base . '/config/secrets-contract.json');
    @rmdir($this->base . '/config');
    @rmdir($this->base . '/web');
    @rmdir($this->base);
    parent::tearDown();
  }

  public function testMaskKeepsOnlyEdgesAndHidesShortValues(): void {
    $this->assertSame('ab••••••••kl', SecretsManager::mask('abcdefghijkl'));
    $this->assertSame('••••••••', SecretsManager::mask('abc'));
  }

  public function testSaveMergesAndKeepsValuesLeftEmpty(): void {
    file_put_contents($this->store(), "GOOGLE_OAUTH_CLIENT_ID=antigo\nEXEMPLO_SENHA=senha-antiga\n");
    $result = $this->manager()->save(['GOOGLE_OAUTH_CLIENT_ID' => 'novo', 'EXEMPLO_SENHA' => '', 'BREVO_API_KEY' => '']);
    $this->assertTrue($result['ok']);
    $this->assertSame(['GOOGLE_OAUTH_CLIENT_ID'], $result['updated']);
    $this->assertSame(
      ['GOOGLE_OAUTH_CLIENT_ID' => 'novo', 'EXEMPLO_SENHA' => 'senha-antiga'],
      SecretsFormat::parse((string) file_get_contents($this->store())),
    );
    $this->assertSame(0640, fileperms($this->store()) & 0777);
  }

  public function testSaveRejectsUnknownNamesAndMultilineValues(): void {
    $this->assertFalse($this->manager()->save(['NAO_EXISTE' => 'x'])['ok']);
    $this->assertFalse($this->manager()->save(['GOOGLE_OAUTH_CLIENT_ID' => "a\nb"])['ok']);
    $this->assertFileDoesNotExist($this->store());
  }

  public function testSaveRefusesValuesTheLoaderWouldAlter(): void {
    // parse_ini_file (INI_SCANNER_RAW) corta em ";", remove aspas e tira espaços das pontas.
    foreach (['a;b', '"quoted"', ' espaco ', 'fim '] as $value) {
      $result = $this->manager()->save(['BREVO_API_KEY' => $value]);
      $this->assertFalse($result['ok'], "valor recusado: $value");
    }
    $this->assertFileDoesNotExist($this->store());
    // Caracteres que o loader preserva continuam aceitos.
    $this->assertTrue($this->manager()->save(['BREVO_API_KEY' => 'abc#def=123'])['ok']);
  }

  public function testDatabaseStorageRefusesToWriteFile(): void {
    $manager = $this->manager('database');
    $this->assertFalse($manager->canSaveHere());
    $this->assertFalse($manager->save(['GOOGLE_OAUTH_CLIENT_ID' => 'x'])['ok']);
    $this->assertFileDoesNotExist($this->store());
  }

  public function testEnvironmentFromDeployerOverridesLocalSetting(): void {
    $this->assertSame('test', $this->manager()->environment());
    mkdir($this->base . '/var/deployer', 0700, TRUE);
    file_put_contents($this->base . '/var/deployer/environment.json', json_encode(['schema' => 1, 'environment' => 'production', 'site' => 'https://example.invalid', 'changed_at' => 'x']));
    $this->assertSame('production', $this->manager()->environment());
    file_put_contents($this->base . '/var/deployer/environment.json', 'não é json');
    $this->assertSame('test', $this->manager()->environment());
    @unlink($this->base . '/var/deployer/environment.json');
    @rmdir($this->base . '/var/deployer');
  }

  private function manager(string $storage = 'file'): SecretsManager {
    $keys = $this->createMock(KeyRepositoryInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    $settings = new Settings([
      'aculta_secrets_file' => $this->store(),
      'aculta_secrets_environment' => 'test',
      'aculta_secrets_storage' => $storage,
    ]);
    return new SecretsManager($keys, $settings, $factory, $this->base . '/web');
  }

  private function store(): string {
    return $this->base . '/secrets/aculta.secrets.env';
  }

  public function testValueReadsServerFileWhenNoKeyExists(): void {
    file_put_contents($this->store(), "BREVO_API_KEY=valor-de-teste\n");
    $this->assertSame('valor-de-teste', $this->manager()->value('BREVO_API_KEY'));
  }

  public function testValuePrefersKeyWhenKeyExists(): void {
    file_put_contents($this->store(), "BREVO_API_KEY=do-arquivo\n");
    $key = $this->createMock(\Drupal\key\KeyInterface::class);
    $key->method('getKeyValue')->willReturn('da-key');
    $keys = $this->createMock(KeyRepositoryInterface::class);
    $keys->method('getKey')->with('brevo_api_key')->willReturn($key);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    $settings = new Settings([
      'aculta_secrets_file' => $this->store(),
      'aculta_secrets_environment' => 'test',
      'aculta_secrets_storage' => 'file',
    ]);
    $manager = new SecretsManager($keys, $settings, $factory, $this->base . '/web');
    $this->assertSame('da-key', $manager->value('BREVO_API_KEY'));
  }

  public function testValueIsNullWhenNeitherKeyNorFileHasIt(): void {
    $this->assertNull($this->manager()->value('BREVO_API_KEY'));
  }

  public function testOtherEnvironmentCredentialIsNeverResolvedOrWritten(): void {
    // Ambiente test: TURNSTILE_KEYS_JSON é só de produção, mesmo que exista no arquivo desta instalação.
    file_put_contents($this->store(), "TURNSTILE_KEYS_JSON=chave-de-producao\n");
    $keys = $this->createMock(KeyRepositoryInterface::class);
    $keys->expects($this->never())->method('getKey');
    $manager = $this->managerWith('test', $this->store(), $keys);

    $this->assertNull($manager->value('TURNSTILE_KEYS_JSON'));
    $this->assertFalse($manager->inActiveScope('TURNSTILE_KEYS_JSON'));
    $this->assertFalse($manager->save(['TURNSTILE_KEYS_JSON' => 'nova'])['ok']);
    $this->assertFalse($manager->clear('TURNSTILE_KEYS_JSON')['ok']);
    $this->assertSame(
      ['TURNSTILE_KEYS_JSON' => 'chave-de-producao'],
      SecretsFormat::parse((string) file_get_contents($this->store())),
    );
  }

  public function testStatusForOtherEnvironmentReadsNoValues(): void {
    $keys = $this->createMock(KeyRepositoryInterface::class);
    $keys->expects($this->never())->method('getKey');
    file_put_contents($this->store(), "TURNSTILE_KEYS_JSON=chave-de-producao\n");
    $rows = $this->managerWith('test', $this->store(), $keys)->statusForEnvironment('production');

    $this->assertSame(['GOOGLE_OAUTH_CLIENT_ID', 'TURNSTILE_KEYS_JSON', 'BREVO_API_KEY'], array_keys($rows));
    foreach ($rows as $row) {
      $this->assertSame(SecretsManager::STATE_ELSEWHERE, $row['state']);
      $this->assertNull($row['present']);
    }
  }

  public function testStatusForActiveEnvironmentReportsRealStates(): void {
    file_put_contents($this->store(), "GOOGLE_OAUTH_CLIENT_ID=ok\n");
    $status = $this->manager()->status();

    $this->assertSame(['GOOGLE_OAUTH_CLIENT_ID', 'EXEMPLO_SENHA', 'BREVO_API_KEY'], array_keys($status));
    $this->assertSame('configured', $status['GOOGLE_OAUTH_CLIENT_ID']['state']);
    $this->assertSame('missing_required', $status['EXEMPLO_SENHA']['state']);
    $this->assertSame('missing_optional', $status['BREVO_API_KEY']['state']);
  }

  public function testSameNameHoldsIndependentValuesPerEnvironment(): void {
    $production = $this->base . '/secrets/producao.env';
    file_put_contents($production, "BREVO_API_KEY=valor-producao\n");
    file_put_contents($this->store(), "BREVO_API_KEY=valor-teste\n");
    $keys = $this->createMock(KeyRepositoryInterface::class);

    $this->assertSame('valor-teste', $this->managerWith('test', $this->store(), $keys)->value('BREVO_API_KEY'));
    $this->assertSame('valor-producao', $this->managerWith('production', $production, $keys)->value('BREVO_API_KEY'));
    @unlink($production);
  }

  private function managerWith(string $environment, string $store, KeyRepositoryInterface $keys): SecretsManager {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    $settings = new Settings([
      'aculta_secrets_file' => $store,
      'aculta_secrets_environment' => $environment,
      'aculta_secrets_storage' => 'file',
    ]);
    return new SecretsManager($keys, $settings, $factory, $this->base . '/web');
  }

}

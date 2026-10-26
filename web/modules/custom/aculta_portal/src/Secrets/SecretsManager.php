<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Secrets;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\key\KeyRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Credenciais do ambiente, gerenciadas pelo painel "Credenciais do ambiente".
 *
 * As chaves são digitadas no formulário e gravadas no arquivo de credenciais (fora do
 * document root, modo sem escrita de grupo e sem acesso de outros). O Drupal lê esse arquivo
 * no bootstrap, pelo Key com provider env. Nenhum valor é gravado no banco, em configuração
 * exportada ou em log: o log registra só nomes e contagens.
 */
final class SecretsManager {

  /** Número de caracteres mantidos visíveis em cada extremidade da máscara. */
  private const MASK_VISIBLE = 2;

  /** Tamanho máximo aceito para um valor (bytes). */
  private const MAX_VALUE_BYTES = 4096;

  private readonly LoggerInterface $logger;

  public function __construct(
    private readonly KeyRepositoryInterface $keys,
    private readonly Settings $settings,
    LoggerChannelFactoryInterface $loggerFactory,
    private readonly string $appRoot,
  ) {
    $this->logger = $loggerFactory->get('aculta_portal');
  }

  /** Arquivo de credenciais lido pelo bootstrap (aculta_secrets_file em settings.local.php). */
  public function storePath(): string {
    return (string) $this->settings->get('aculta_secrets_file', dirname($this->appRoot) . '/secrets/aculta.secrets.env');
  }

  /** Ambiente do contrato (production|test). Padrão: production. */
  public function environment(): string {
    // O ambiente definido pelo deployer (arquivo neutro) tem precedência sobre a configuração local.
    $env = (string) $this->settings->get('aculta_secrets_environment', 'production');
    return \Drupal\aculta_portal\Environment\DeployerEnvironment::current(dirname($this->appRoot), $env);
  }

  /**
   * Armazenamento das credenciais neste ambiente: file (arquivo local, homelab e teste) ou
   * database (banco de dados criptografado, produção; provisionado pelo ACULTA Deployer após o deploy).
   */
  public function storageMode(): string {
    $mode = (string) $this->settings->get('aculta_secrets_storage', 'file');
    return $mode === 'database' ? 'database' : 'file';
  }

  /** Verdadeiro quando o painel pode gravar o arquivo de credenciais neste ambiente. */
  public function canSaveHere(): bool {
    return $this->storageMode() === 'file';
  }

  /** Ambientes do contrato, na ordem das abas do painel. */
  public const ENVIRONMENTS = ['production', 'test'];

  /** Estado de credenciais de outro ambiente: o servidor atual não consulta nem afirma nada sobre elas. */
  public const STATE_ELSEWHERE = 'managed_elsewhere';

  /** @return array<string, mixed> */
  public function contract(): array {
    $file = dirname($this->appRoot) . '/config/secrets-contract.json';
    $data = is_file($file) ? json_decode((string) file_get_contents($file), TRUE) : NULL;
    return is_array($data) ? $data : [];
  }

  /**
   * Verdadeiro quando a credencial pertence ao ambiente ativo. É o único critério de leitura e escrita:
   * uma credencial de outro ambiente nunca é resolvida nem gravada neste servidor, mesmo com o mesmo nome.
   */
  public function inActiveScope(string $name): bool {
    $variable = $this->variable($name);
    return $variable !== NULL && in_array($this->environment(), (array) ($variable['environments'] ?? []), TRUE);
  }

  /**
   * Metadados públicos de um ambiente (nome, Key e obrigatoriedade). Não consulta valores nem fontes.
   *
   * @return array<string, array{key_id: string, required: bool}>
   */
  public function listForEnvironment(string $environment): array {
    $rows = [];
    foreach ($this->contract()['variables'] ?? [] as $variable) {
      if (!in_array($environment, (array) ($variable['environments'] ?? []), TRUE)) {
        continue;
      }
      $rows[(string) $variable['name']] = [
        'key_id' => (string) ($variable['key_id'] ?? ''),
        'required' => in_array($environment, (array) ($variable['required_in'] ?? []), TRUE),
      ];
    }
    return $rows;
  }

  /**
   * Estado de um ambiente. Estado real só para o ambiente ativo; para os demais, state é
   * STATE_ELSEWHERE e present é NULL, sem leitura de nenhuma fonte.
   *
   * @return array<string, array{key_id: string, required: bool, state: string, present: ?bool}>
   */
  public function statusForEnvironment(string $environment): array {
    $active = $environment === $this->environment();
    $rows = [];
    foreach ($this->listForEnvironment($environment) as $name => $meta) {
      if (!$active) {
        $rows[$name] = $meta + ['state' => self::STATE_ELSEWHERE, 'present' => NULL];
        continue;
      }
      $present = $this->value($name) !== NULL;
      $state = match (TRUE) {
        $present => 'configured',
        $meta['required'] => 'missing_required',
        default => 'missing_optional',
      };
      $rows[$name] = $meta + ['state' => $state, 'present' => $present];
    }
    return $rows;
  }

  /**
   * Estado das credenciais do ambiente ativo. Não retorna valores.
   *
   * @return array<string, array{key_id: string, required: bool, state: string, present: ?bool}>
   */
  public function status(): array {
    return $this->statusForEnvironment($this->environment());
  }

  /** @return string[] obrigatórias no ambiente sem valor. */
  public function missingRequired(): array {
    $missing = [];
    foreach ($this->status() as $name => $row) {
      if ($row['required'] && !$row['present']) {
        $missing[] = $name;
      }
    }
    return $missing;
  }

  /**
   * Valor de uma variável do contrato, lido pelo Key (o mesmo caminho que o runtime usa), ou NULL.
   * Uso restrito: a página de revelação e a máscara. Nunca registrar nem persistir o retorno.
   */
  public function value(string $name): ?string {
    // Escopo do backend: credencial de outro ambiente não é resolvida aqui, mesmo que exista nesta instalação.
    if (!$this->inActiveScope($name)) {
      return NULL;
    }
    $variable = $this->variable($name);
    if ($variable === NULL) {
      return NULL;
    }
    // Drupal Key é a interface única para integrações (docs/operations/SECRETS.md): uma credencial com Key (provedor
    // env) é lida por ele. Sem Key, o valor vem do arquivo de credenciais do servidor, como fallback.
    $key = $this->keys->getKey((string) $variable['key_id']);
    if ($key !== NULL) {
      $value = $key->getKeyValue();
      return is_string($value) && $value !== '' ? $value : NULL;
    }
    return $this->fileValue($name);
  }

  /** Valor de uma variável no arquivo de credenciais do servidor, ou NULL. Nunca registrado. */
  private function fileValue(string $name): ?string {
    $store = $this->storePath();
    if (!is_file($store) || !is_readable($store)) {
      return NULL;
    }
    $parsed = SecretsFormat::parse((string) file_get_contents($store));
    $value = $parsed[$name] ?? NULL;
    return is_string($value) && $value !== '' ? $value : NULL;
  }

  /** Máscara para exibição: mantém poucos caracteres nas pontas. Valores curtos viram só bolinhas. */
  public static function mask(string $value): string {
    $length = mb_strlen($value);
    if ($length <= 2 * self::MASK_VISIBLE + 2) {
      return str_repeat('•', 8);
    }
    return mb_substr($value, 0, self::MASK_VISIBLE) . str_repeat('•', 8) . mb_substr($value, -self::MASK_VISIBLE);
  }

  /**
   * Salva valores digitados. Campo vazio mantém o valor atual. Só nomes do contrato são aceitos.
   *
   * @param array<string, string> $submitted nome => valor digitado
   * @return array{ok: bool, message: string, updated: string[]}
   */
  public function save(array $submitted): array {
    $fail = static fn(string $message): array => ['ok' => FALSE, 'message' => $message, 'updated' => []];
    if (!$this->canSaveHere()) {
      return $fail('Armazenamento em banco de dados: o provisionamento é feito pelo ACULTA Deployer após o deploy.');
    }
    $allowed = SecretsFormat::allowedNames($this->contract());
    $changes = [];
    foreach ($submitted as $name => $value) {
      if (!in_array($name, $allowed, TRUE)) {
        return $fail('Variável fora do contrato: ' . $name . '.');
      }
      if (!$this->inActiveScope($name)) {
        return $fail('Variável de outro ambiente, não gravada aqui: ' . $name . '.');
      }
      if ($value === '') {
        continue;
      }
      if (strlen($value) > self::MAX_VALUE_BYTES || preg_match('/[\r\n]/', $value) === 1) {
        return $fail('Valor inválido para ' . $name . ': uma linha, até ' . self::MAX_VALUE_BYTES . ' bytes.');
      }
      $changes[$name] = $value;
    }
    return $this->persist($changes, []);
  }

  /**
   * Apaga o valor de uma variável do contrato: a linha sai do arquivo de credenciais. Só o nome vai ao log.
   *
   * @return array{ok: bool, message: string, updated: string[]}
   */
  public function clear(string $name): array {
    if (!$this->canSaveHere()) {
      return ['ok' => FALSE, 'message' => 'Armazenamento em banco de dados: o provisionamento é feito pelo ACULTA Deployer após o deploy.', 'updated' => []];
    }
    if (!in_array($name, SecretsFormat::allowedNames($this->contract()), TRUE)) {
      return ['ok' => FALSE, 'message' => 'Variável fora do contrato: ' . $name . '.', 'updated' => []];
    }
    if (!$this->inActiveScope($name)) {
      return ['ok' => FALSE, 'message' => 'Variável de outro ambiente, não apagada aqui: ' . $name . '.', 'updated' => []];
    }
    return $this->persist([], [$name]);
  }

  /**
   * Única escrita do arquivo de credenciais: aplica alterações e remoções sobre o conteúdo atual,
   * na ordem do contrato, de forma atômica.
   *
   * @param array<string, string> $changes nome => valor novo
   * @param string[] $removals nomes a apagar
   * @return array{ok: bool, message: string, updated: string[]}
   */
  private function persist(array $changes, array $removals): array {
    $fail = static fn(string $message): array => ['ok' => FALSE, 'message' => $message, 'updated' => []];
    $allowed = SecretsFormat::allowedNames($this->contract());
    $store = $this->storePath();
    $current = [];
    if (is_file($store)) {
      $parsed = SecretsFormat::parse((string) file_get_contents($store));
      if ($parsed === NULL) {
        return $fail('O arquivo de credenciais tem linhas fora do formato NAME=value. Corrija-o antes de salvar.');
      }
      $current = $parsed;
    }
    $merged = array_diff_key(array_merge($current, $changes), array_flip($removals));
    $lines = [];
    foreach ($allowed as $name) {
      if (isset($merged[$name]) && $merged[$name] !== '') {
        $lines[] = $name . '=' . $merged[$name];
      }
    }
    if (!$this->writeAtomic($store, implode("\n", $lines) . "\n")) {
      return $fail('Não foi possível gravar o arquivo de credenciais.');
    }
    $updated = array_keys($changes);
    if ($removals !== []) {
      $this->logger->notice('Credenciais do ambiente: valor apagado (@names).', ['@names' => implode(', ', $removals)]);
      return [
        'ok' => TRUE,
        'message' => 'Valor apagado. A mudança vale a partir da próxima requisição.',
        'updated' => $removals,
      ];
    }
    $this->logger->notice('Credenciais do ambiente: @count variável(eis) atualizada(s) (@names).', [
      '@count' => count($updated),
      '@names' => $updated === [] ? 'nenhuma' : implode(', ', $updated),
    ]);
    return [
      'ok' => TRUE,
      'message' => $updated === [] ? 'Nada foi alterado.' : 'Credenciais salvas. Valores novos valem a partir da próxima requisição.',
      'updated' => $updated,
    ];
  }

  /** Registra no log a revelação de um valor (nome e usuário; nunca o valor). */
  public function logReveal(string $name, string $account): void {
    $this->logger->notice('Credencial revelada no painel: @name por @account.', ['@name' => $name, '@account' => $account]);
  }

  /** @return array<string, mixed>|null */
  private function variable(string $name): ?array {
    foreach ($this->contract()['variables'] ?? [] as $variable) {
      if (($variable['name'] ?? NULL) === $name) {
        return $variable;
      }
    }
    return NULL;
  }

  private function writeAtomic(string $target, string $content): bool {
    $dir = dirname($target);
    if (!is_dir($dir) && !mkdir($dir, 0700, TRUE)) {
      return FALSE;
    }
    $temp = $dir . DIRECTORY_SEPARATOR . '.aculta-save-' . bin2hex(random_bytes(8)) . '.tmp';
    $fh = @fopen($temp, 'xb');
    if ($fh === FALSE) {
      return FALSE;
    }
    chmod($temp, 0640);
    $ok = fwrite($fh, $content) === strlen($content);
    fflush($fh);
    fsync($fh);
    fclose($fh);
    if (!$ok || !rename($temp, $target)) {
      @unlink($temp);
      return FALSE;
    }
    chmod($target, 0640);
    return TRUE;
  }

}

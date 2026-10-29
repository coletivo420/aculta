<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Registro de correções de deploy (canônicos, sitemap e páginas que apontam
 * para produção). Fonte única: registry/deploy-registry.json.
 */
final class Registry {

  public const KINDS = ['canonical', 'sitemap', 'link', 'redirect', 'other'];
  public const STATUSES = ['open', 'resolved'];

  /**
   * @var array<int, array<string, mixed>> */
  private array $entries;

  /**
   * Digest SHA-256 do arquivo no momento da leitura, para detectar gravação concorrente. */
  private string $loadedDigest;

  public function __construct(private readonly string $path) {
    $raw = (string) file_get_contents($path);
    $data = json_decode($raw, TRUE, 512, JSON_THROW_ON_ERROR);
    if (($data['schema'] ?? NULL) !== 1 || !is_array($data['entries'] ?? NULL)) {
      throw new \RuntimeException('Registro inválido: schema 1 com lista "entries" é obrigatório.');
    }
    $this->entries = $data['entries'];
    $this->loadedDigest = hash('sha256', $raw);
  }

  /**
   * @return array<int, array<string, mixed>> */
  public function entries(): array {
    return $this->entries;
  }

  /**
   * @return string[] Erros de validação; vazio quando o registro está correto. */
  public function validate(): array {
    $errors = [];
    $ids = [];
    foreach ($this->entries as $i => $e) {
      $where = 'entrada #' . ($i + 1) . ' (' . ($e['id'] ?? '?') . ')';
      foreach (['id', 'kind', 'status', 'page', 'current', 'expected_production', 'reason', 'owner', 'decision'] as $field) {
        if (!isset($e[$field]) || trim((string) $e[$field]) === '') {
          $errors[] = "$where: campo obrigatório ausente: $field";
        }
      }
      if (isset($e['id']) && preg_match('/^DEP-\d{4}$/', (string) $e['id']) !== 1) {
        $errors[] = "$where: id deve seguir DEP-NNNN";
      }
      if (isset($e['id']) && in_array($e['id'], $ids, TRUE)) {
        $errors[] = "$where: id duplicado";
      }
      $ids[] = $e['id'] ?? NULL;
      if (isset($e['kind']) && !in_array($e['kind'], self::KINDS, TRUE)) {
        $errors[] = "$where: kind inválido: {$e['kind']}";
      }
      if (isset($e['status']) && !in_array($e['status'], self::STATUSES, TRUE)) {
        $errors[] = "$where: status inválido: {$e['status']}";
      }
      if (isset($e['blocking']) && !is_bool($e['blocking'])) {
        $errors[] = "$where: blocking deve ser booleano";
      }
    }
    return $errors;
  }

  /**
   * @return array<int, array<string, mixed>> Entradas abertas. */
  public function open(): array {
    return array_values(array_filter($this->entries, static fn(array $e): bool => ($e['status'] ?? '') === 'open'));
  }

  /**
   * @return array<int, array<string, mixed>> Entradas abertas e bloqueantes. */
  public function openBlocking(): array {
    return array_values(array_filter($this->open(), static fn(array $e): bool => ($e['blocking'] ?? FALSE) === TRUE));
  }

  /**
   * Grava o registro de forma atômica: escreve em arquivo temporário e renomeia.
   *
   * Controle otimista: sob o bloqueio exclusivo (.lock), recusa gravar se o arquivo mudou desde a leitura.
   * Sem isso, duas gravações concorrentes sobrescreveriam uma à outra e poderiam atribuir o mesmo id.
   */
  public function save(): void {
    $lock = fopen($this->path . '.lock', 'c');
    if ($lock === FALSE || !flock($lock, LOCK_EX)) {
      throw new \RuntimeException('Não foi possível obter o bloqueio do registro.');
    }
    try {
      if (hash('sha256', (string) @file_get_contents($this->path)) !== $this->loadedDigest) {
        throw new \RuntimeException('O registro foi alterado por outro processo desde a leitura; releia e repita a operação.');
      }
      $json = json_encode(['schema' => 1, 'entries' => $this->entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
      $tmp = $this->path . '.tmp-' . getmypid();
      if (file_put_contents($tmp, $json) === FALSE || !rename($tmp, $this->path)) {
        @unlink($tmp);
        throw new \RuntimeException('Falha ao gravar o registro.');
      }
      $this->loadedDigest = hash('sha256', $json);
    } finally {
      flock($lock, LOCK_UN);
      fclose($lock);
    }
  }

  /**
   * Importa vários achados de uma vez, tudo ou nada: se qualquer achado for
   * inválido, nada é gravado. Campos desconhecidos são descartados.
   *
   * @param array<int, array<string, mixed>> $findings
   *
   * @return string[] Ids criados.
   */
  public function addMany(array $findings): array {
    $backup = $this->entries;
    $ids = [];
    foreach ($findings as $i => $f) {
      if (!is_array($f)) {
        $this->entries = $backup;
        throw new \InvalidArgumentException("achado #" . ($i + 1) . " não é um objeto");
      }
      $max = 0;
      foreach ($this->entries as $e) {
        $max = max($max, (int) substr((string) $e['id'], 4));
      }
      $entry = ['id' => sprintf('DEP-%04d', $max + 1)] + [
        'kind' => $f['kind'] ?? NULL,
        'status' => 'open',
        'blocking' => (bool) ($f['blocking'] ?? FALSE),
        'page' => $f['page'] ?? NULL,
        'current' => $f['current'] ?? NULL,
        'expected_production' => $f['expected_production'] ?? NULL,
        'reason' => $f['reason'] ?? NULL,
        'owner' => $f['owner'] ?? NULL,
        'decision' => 'pendente',
      ];
      $this->entries[] = $entry;
      $ids[] = $entry['id'];
    }
    $errors = $this->validate();
    if ($errors !== []) {
      $this->entries = $backup;
      throw new \InvalidArgumentException(implode('; ', $errors));
    }
    $this->save();
    return $ids;
  }

  /**
   * Acrescenta uma entrada e grava o arquivo. Retorna o id criado. */
  public function add(array $fields): string {
    $max = 0;
    foreach ($this->entries as $e) {
      $max = max($max, (int) substr((string) $e['id'], 4));
    }
    $fields = ['id' => sprintf('DEP-%04d', $max + 1)] + $fields + ['status' => 'open', 'blocking' => FALSE, 'decision' => 'pendente'];
    $this->entries[] = $fields;
    $errors = $this->validate();
    if ($errors !== []) {
      array_pop($this->entries);
      throw new \InvalidArgumentException(implode('; ', $errors));
    }
    $this->save();
    return $fields['id'];
  }

}

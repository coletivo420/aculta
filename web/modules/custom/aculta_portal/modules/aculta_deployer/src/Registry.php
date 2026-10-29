<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Reads and writes the deploy correction registry.
 *
 * The single source of truth is registry/deploy-registry.json.
 */
final class Registry {

  public const KINDS = ['canonical', 'sitemap', 'link', 'redirect', 'other'];
  public const STATUSES = ['open', 'resolved'];

  /**
   * Stores the validated registry entries.
   *
   * @var array<int, array<string, mixed>>
   */
  private array $entries;

  /**
   * SHA-256 digest captured at load time to detect concurrent writes.
   *
   * @var string
   */
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
   * Returns all registry entries.
   *
   * @return array<int, array<string, mixed>>
   *   Registry entries.
   */
  public function entries(): array {
    return $this->entries;
  }

  /**
   * Validates the registry schema and values.
   *
   * @return string[]
   *   Validation errors, or an empty array when valid.
   */
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
   * Returns open registry entries.
   *
   * @return array<int, array<string, mixed>>
   *   Open entries.
   */
  public function open(): array {
    return array_values(array_filter($this->entries, static fn(array $e): bool => ($e['status'] ?? '') === 'open'));
  }

  /**
   * Returns open entries that block release.
   *
   * @return array<int, array<string, mixed>>
   *   Open blocking entries.
   */
  public function openBlocking(): array {
    return array_values(array_filter($this->open(), static fn(array $e): bool => ($e['blocking'] ?? FALSE) === TRUE));
  }

  /**
   * Grava o registro com um arquivo temporário e rename.
   *
   * Recusa gravar se o arquivo mudou desde a leitura sob lock exclusivo.
   * Sem isso, duas gravações concorrentes
   * poderiam sobrescrever entradas ou atribuir o mesmo ID.
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
      $json = json_encode(
        ['schema' => 1, 'entries' => $this->entries],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
      ) . "\n";
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
   * Imports validated findings atomically and discards unknown fields.
   *
   * @param array<int, array<string, mixed>> $findings
   *   Findings to validate and add.
   *
   * @return string[]
   *   IDs created for the findings.
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
   * Adds an entry and saves the registry.
   *
   * @param array<string, mixed> $fields
   *   Fields to validate and store.
   *
   * @return string
   *   ID assigned to the entry.
   */
  public function add(array $fields): string {
    $max = 0;
    foreach ($this->entries as $e) {
      $max = max($max, (int) substr((string) $e['id'], 4));
    }
    $fields = ['id' => sprintf('DEP-%04d', $max + 1)] + $fields + [
      'status' => 'open',
      'blocking' => FALSE,
      'decision' => 'pendente',
    ];
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

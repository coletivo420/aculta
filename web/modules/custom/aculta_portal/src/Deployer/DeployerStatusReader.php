<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Deployer;

/**
 * Lê o relatório neutro publicado pela ferramenta de deploy (var/deployer/status.json).
 *
 * O Portal não executa a ferramenta: só lê o arquivo, que contém nomes, contagens e estados,
 * nunca valores de credenciais. Qualquer falha de leitura vira estado "indisponível".
 */
final class DeployerStatusReader {

  public function __construct(private readonly string $appRoot) {}

  /**
   * Caminho do relatório (fora do document root). */
  public function path(): string {
    return dirname($this->appRoot) . '/var/deployer/status.json';
  }

  /**
   * Caminho do estado persistente do Runtime Sync v2 (fora do document root). */
  public function runtimeSyncStatePath(): string {
    return dirname($this->appRoot) . '/var/runtime-sync/state.json';
  }

  /**
   * Caminho do último relatório de execução do Runtime Sync v2. */
  public function runtimeSyncLatestPath(): string {
    return dirname($this->appRoot) . '/var/runtime-sync/latest.json';
  }

  /**
   * Resultado da última verificação de indexação publicado pelo deployer (`indexing`), ou null. */
  public function indexing(): ?array {
    $data = $this->read();
    $indexing = is_array($data) ? ($data['indexing'] ?? NULL) : NULL;
    return is_array($indexing) ? $indexing : NULL;
  }

  /**
   * Lê o relatório de status publicado pelo deployer.
   *
   * @return array<string, mixed>|null
   *   Conteúdo do relatório, ou NULL se o arquivo não existe, não pode ser
   *   lido ou tem outro esquema.
   */
  public function read(): ?array {
    $file = $this->path();
    if (!is_file($file) || is_link($file) || !is_readable($file)) {
      return NULL;
    }
    $data = json_decode((string) file_get_contents($file), TRUE);
    return is_array($data) && ($data['schema'] ?? NULL) === 1 ? $data : NULL;
  }

  /**
   * Lê de forma somente leitura o estado neutro do Runtime Sync v2.
   *
   * Não executa comandos nem altera `var/deployer/status.json` (schema 1).
   * Rejeita symlinks, arquivos ilegíveis ou com `schema_version !== 2`.
   *
   * @return array<string, mixed>|null
   *   Dados consolidados de `state.json` e `latest.json` (schema_version 2),
   *   ou NULL se indisponível/inválido.
   */
  public function readRuntimeSync(): ?array {
    $stateFile = $this->runtimeSyncStatePath();
    $stateDir = dirname($stateFile);
    if (!is_dir($stateDir) || is_link($stateDir) || !is_file($stateFile) || is_link($stateFile) || !is_readable($stateFile)) {
      return NULL;
    }
    $state = json_decode((string) file_get_contents($stateFile), TRUE);
    if (!is_array($state) || ($state['schema_version'] ?? NULL) !== 2) {
      return NULL;
    }
    $latest = NULL;
    $latestFile = $this->runtimeSyncLatestPath();
    if (is_file($latestFile) && !is_link($latestFile) && is_readable($latestFile)) {
      $decodedLatest = json_decode((string) file_get_contents($latestFile), TRUE);
      if (is_array($decodedLatest) && ($decodedLatest['schema_version'] ?? NULL) === 2) {
        $latest = $decodedLatest;
      }
    }
    $state['latest_report'] = $latest;
    return $state;
  }

}

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

  /** Caminho do relatório (fora do document root). */
  public function path(): string {
    return dirname($this->appRoot) . '/var/deployer/status.json';
  }

  /**
   * @return array<string, mixed>|null null se o arquivo não existe, não pode ser lido ou tem outro esquema.
   */
  /** Resultado da última verificação de indexação publicado pelo deployer (`indexing`), ou null. */
  public function indexing(): ?array {
    $data = $this->read();
    $indexing = is_array($data) ? ($data['indexing'] ?? NULL) : NULL;
    return is_array($indexing) ? $indexing : NULL;
  }

  public function read(): ?array {
    $file = $this->path();
    if (!is_file($file) || !is_readable($file)) {
      return NULL;
    }
    $data = json_decode((string) file_get_contents($file), TRUE);
    return is_array($data) && ($data['schema'] ?? NULL) === 1 ? $data : NULL;
  }

}

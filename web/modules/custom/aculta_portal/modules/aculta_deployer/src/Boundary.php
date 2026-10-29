<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Valida as fronteiras de dependência do Deployer.
 *
 * O Portal e o tema não podem executar a ferramenta, e a ferramenta não pode
 * depender deles nem do Drupal. As listas ficam em config/boundary.json.
 */
final class Boundary {

  /**
   * Cria o validador com as raízes e regras do repositório.
   *
   * @param string $repoRoot
   *   Raiz do repositório.
   * @param string $toolRoot
   *   Raiz do módulo Deployer.
   * @param array<string, mixed> $rules
   *   Conteúdo de config/boundary.json.
   */
  public function __construct(
    private readonly string $repoRoot,
    private readonly string $toolRoot,
    private readonly array $rules,
  ) {}

  /**
   * Encontra referências que cruzam as fronteiras permitidas.
   *
   * @return string[]
   *   Violações encontradas.
   */
  public function check(): array {
    $violations = [];
    foreach ($this->rules['consumers'] as $dir) {
      $abs = $this->repoRoot . '/' . $dir;
      if (!is_dir($abs)) {
        continue;
      }
      foreach ($this->files($abs) as $file) {
        $rel = $this->rel($file);
        // The submodule itself is the tool; it may name itself.
        if (array_filter($this->rules['consumer_exclude'] ?? [], static fn(string $x): bool => str_starts_with($rel, $x . '/'))) {
          continue;
        }
        $text = (string) file_get_contents($file);
        // Adaptadores explicitamente read-only podem citar o contrato de dados
        // da ferramenta, sem executá-la nem importar suas classes.
        if ($this->consumerReferenceAllowed($rel)) {
          foreach ($this->rules['consumer_allowed_forbidden'] ?? [] as $token) {
            if (str_contains($text, $token)) {
              $violations[] = $rel . " usa '$token' na pasta de leitura neutra; "
              . 'o Portal não executa a ferramenta';
            }
          }
          continue;
        }
        foreach ($this->rules['tool_terms'] as $term) {
          if (str_contains($text, $term)) {
            $violations[] = $this->rel($file) . " referencia a ferramenta ('$term'); "
              . 'consumidores não podem depender do submódulo aculta_deployer';
          }
        }
      }
    }
    foreach ($this->rules['tool_dirs'] as $dir) {
      $abs = $this->toolRoot . '/' . $dir;
      if (!is_dir($abs)) {
        continue;
      }
      foreach ($this->files($abs) as $file) {
        // The rules file is data, not a dependency.
        if (realpath($file) === realpath($this->toolRoot . '/config/boundary.json')) {
          continue;
        }
        $text = (string) file_get_contents($file);
        foreach ($this->rules['forbidden_dependencies'] as $dep) {
          if (str_contains($text, $dep)) {
            $violations[] = $this->rel($file) . " depende de '$dep'; a ferramenta é standalone e não usa Drupal, Portal, tema ou vendor";
          }
        }
      }
    }
    return $violations;
  }

  /**
   * Checks whether a consumer file is allowed to read the data contract.
   *
   * Directories are reserved for neutral reporting adapters. Other
   * integrations must be listed as individual files to keep the exception
   * narrow.
   */
  private function consumerReferenceAllowed(string $relativePath): bool {
    $allowedFiles = $this->rules['consumer_tool_reference_allowed_files'] ?? [];
    if (in_array($relativePath, $allowedFiles, TRUE)) {
      return TRUE;
    }
    foreach ($this->rules['consumer_tool_reference_allowed'] ?? [] as $directory) {
      if (str_starts_with($relativePath, rtrim($directory, '/') . '/')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Lista arquivos não Markdown sob um diretório.
   *
   * @return string[]
   *   Caminhos absolutos encontrados.
   */
  private function files(string $dir): array {
    $out = [];
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
      if ($f->isFile() && !str_ends_with($f->getFilename(), '.md')) {
        $out[] = $f->getPathname();
      }
    }
    return $out;
  }

  /**
   * Converte um caminho absoluto em caminho relativo ao repositório.
   */
  private function rel(string $abs): string {
    return ltrim(str_replace($this->repoRoot, '', $abs), '/');
  }

}

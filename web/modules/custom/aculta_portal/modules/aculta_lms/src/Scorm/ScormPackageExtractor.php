<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Scorm;

/**
 * Extrai um pacote SCORM validado para um diretório privado isolado.
 *
 * O destino é um diretório pai (ex.: sob private://). A extração cria um subdiretório
 * próprio, grava cada entrada com criação exclusiva e confere se o caminho resolvido
 * continua dentro dele. Em qualquer erro, o subdiretório inteiro é removido.
 * Sem dependência do Drupal: o chamador escolhe o diretório pai.
 */
final class ScormPackageExtractor {

  /**
   * Constructs the extractor.
   */
  public function __construct(
    private readonly ScormPackageValidator $validator = new ScormPackageValidator(),
    private readonly ScormManifestParser $parser = new ScormManifestParser(),
  ) {}

  /**
   * Valida, extrai e lê o manifesto.
   *
   * @return array{root: string, manifest: array<string, mixed>}
   *   Diretório extraído e o manifesto lido.
   *
   * @throws \UnexpectedValueException
   *   Quando o pacote é recusado. O diretório extraído é removido antes da exceção sair.
   * @throws \RuntimeException
   *   Quando o diretório pai não existe ou não pode ser usado.
   */
  public function extract(string $zipPath, string $parentDirectory): array {
    $parent = realpath($parentDirectory);
    if ($parent === FALSE || !is_dir($parent) || !is_writable($parent)) {
      throw new \RuntimeException('O diretório de extração do pacote SCORM não está disponível.');
    }
    $root = $parent . '/scorm-' . bin2hex(random_bytes(16));
    if (!mkdir($root, 0700)) {
      throw new \RuntimeException('Não foi possível preparar a extração do pacote SCORM.');
    }
    try {
      $this->validator->assertValid($zipPath);
      $this->writeEntries($zipPath, $root);
      $manifestPath = $root . '/' . ScormPackageValidator::MANIFEST;
      $xml = (string) file_get_contents($manifestPath);
      return ['root' => $root, 'manifest' => $this->parser->parse($xml)];
    }
    catch (\Throwable $error) {
      $this->removeTree($root);
      throw $error;
    }
  }

  /**
   * Grava as entradas de arquivo dentro do destino, conferindo cada caminho resolvido.
   */
  private function writeEntries(string $zipPath, string $root): void {
    $zip = new \ZipArchive();
    if ($zip->open($zipPath, \ZipArchive::RDONLY) !== TRUE) {
      throw new \UnexpectedValueException('O pacote SCORM não é um ZIP válido.');
    }
    try {
      for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = (string) $zip->getNameIndex($index);
        $target = $root . '/' . $name;
        if (str_ends_with($name, '/')) {
          $this->ensureDirectory($target, $root);
          continue;
        }
        $this->ensureDirectory(dirname($target), $root);
        $this->copyEntry($zip, $name, $target);
      }
    }
    finally {
      $zip->close();
    }
  }

  /**
   * Cria o diretório e confere que ele fica dentro da raiz da extração.
   */
  private function ensureDirectory(string $directory, string $root): void {
    if (!is_dir($directory) && !mkdir($directory, 0700, TRUE) && !is_dir($directory)) {
      throw new \RuntimeException('Não foi possível criar um diretório do pacote SCORM.');
    }
    $real = realpath($directory);
    if ($real === FALSE || !str_starts_with($real . '/', $root . '/')) {
      throw new \UnexpectedValueException('O pacote SCORM tenta gravar fora do diretório de extração.');
    }
  }

  /**
   * Copia uma entrada com criação exclusiva, sem seguir link existente no destino.
   */
  private function copyEntry(\ZipArchive $zip, string $name, string $target): void {
    $source = $zip->getStream($name);
    if ($source === FALSE) {
      throw new \UnexpectedValueException('O pacote SCORM contém uma entrada que não pode ser lida.');
    }
    $output = @fopen($target, 'xb');
    if ($output === FALSE) {
      throw new \UnexpectedValueException('O pacote SCORM tenta sobrescrever um arquivo.');
    }
    try {
      stream_copy_to_stream($source, $output);
    }
    finally {
      fclose($output);
      fclose($source);
    }
  }

  /**
   * Remove um diretório sem seguir links simbólicos.
   */
  private function removeTree(string $path): void {
    if (!is_dir($path) || is_link($path)) {
      @unlink($path);
      return;
    }
    $items = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
      if ($item->isDir() && !$item->isLink()) {
        rmdir($item->getPathname());
      }
      else {
        unlink($item->getPathname());
      }
    }
    rmdir($path);
  }

}

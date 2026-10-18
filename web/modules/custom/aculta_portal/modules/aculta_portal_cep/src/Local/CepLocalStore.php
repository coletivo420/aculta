<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Local;

use Drupal\Core\File\FileSystemInterface;

/**
 * Base local de CEPs em arquivos (sem banco de dados), dividida por prefixo.
 *
 * Cada CEP vai para o arquivo do seu prefixo (os três primeiros dígitos), em `private://aculta_portal_cep/shards/`.
 * Cada linha é um registro, separado por TAB: cep, logradouro, bairro, cidade, UF, código IBGE. Os arquivos ficam fora
 * do Git e são gerados pelo comando `aculta-portal-cep:import`.
 *
 * Origem dos dados: base de CEPs do DNE dos Correios, usada sob risco de licença aceito pelo responsável (2026-10-18).
 * Ver docs/portal/CEP-LOCAL.md.
 */
final class CepLocalStore {

  public const DIRECTORY = 'private://aculta_portal_cep/shards';

  /** Versão da base local: data da importação, quantidade de CEPs e origem. Lido pelo status do Portal. */
  public const MANIFEST = 'private://aculta_portal_cep/manifest.json';

  public function __construct(private readonly FileSystemInterface $fileSystem) {}

  /**
   * Endereço do CEP na base local, no mesmo formato da consulta externa. NULL quando não existe ou não há base.
   *
   * @return array<string, string>|null
   */
  public function lookup(string $cep): ?array {
    $cep = preg_replace('/\D+/', '', $cep) ?? '';
    if (strlen($cep) !== 8) {
      return NULL;
    }
    $path = $this->fileSystem->realpath(self::DIRECTORY . '/' . substr($cep, 0, 3) . '.tsv');
    if ($path === FALSE || !is_file($path)) {
      return NULL;
    }
    $handle = fopen($path, 'rb');
    if ($handle === FALSE) {
      return NULL;
    }
    try {
      while (($line = fgets($handle)) !== FALSE) {
        if (str_starts_with($line, $cep . "\t")) {
          return $this->mapped($cep, rtrim($line, "\r\n"));
        }
      }
    }
    finally {
      fclose($handle);
    }
    return NULL;
  }

  /**
   * Grava a base a partir de um TSV ordenado por CEP. Retorna a quantidade de registros gravados.
   *
   * @throws \RuntimeException Quando o arquivo de origem não pode ser lido ou tem linha inválida.
   */
  public function importTsv(string $source): int {
    $handle = fopen($source, 'rb');
    if ($handle === FALSE) {
      throw new \RuntimeException('Não foi possível ler o arquivo de origem.');
    }
    $directory = self::DIRECTORY;
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $count = 0;
    $prefix = NULL;
    $buffer = '';
    try {
      while (($line = fgets($handle)) !== FALSE) {
        $fields = explode("\t", rtrim($line, "\r\n"));
        if (count($fields) !== 6 || !preg_match('/^\d{8}$/', $fields[0])) {
          throw new \RuntimeException('Linha inválida no arquivo de origem (esperado 6 campos e CEP de 8 dígitos).');
        }
        $current = substr($fields[0], 0, 3);
        if ($prefix !== NULL && $current !== $prefix) {
          $this->writeShard($prefix, $buffer);
          $buffer = '';
        }
        $prefix = $current;
        $buffer .= implode("\t", $fields) . "\n";
        $count++;
      }
      if ($prefix !== NULL) {
        $this->writeShard($prefix, $buffer);
      }
    }
    finally {
      fclose($handle);
    }
    return $count;
  }

  /** Grava a versão da base local (data da importação, quantidade e arquivo de origem). */
  public function writeManifest(string $version, int $records, string $source): void {
    $manifest = ['version' => $version, 'records' => $records, 'source' => $source];
    $this->fileSystem->prepareDirectory(dirname(self::MANIFEST), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData((string) json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), self::MANIFEST, FileSystemInterface::EXISTS_REPLACE);
  }

  /**
   * Versão registrada da base local (['version' => 'Y-m-d', 'records' => int, 'source' => string]), ou NULL.
   *
   * @return array<string, mixed>|null
   */
  public function manifest(): ?array {
    $path = $this->fileSystem->realpath(self::MANIFEST);
    if ($path === FALSE || !is_file($path)) {
      return NULL;
    }
    $data = json_decode((string) file_get_contents($path), TRUE);
    return is_array($data) && isset($data['version']) ? $data : NULL;
  }

  /** Quantidade de arquivos de prefixo (shards) presentes na base local. */
  public function shardCount(): int {
    $path = $this->fileSystem->realpath(self::DIRECTORY);
    return $path === FALSE ? 0 : count(glob($path . '/*.tsv') ?: []);
  }

  private function writeShard(string $prefix, string $content): void {
    $uri = self::DIRECTORY . '/' . $prefix . '.tsv';
    $this->fileSystem->saveData($content, $uri, FileSystemInterface::EXISTS_REPLACE);
  }

  /** @return array<string, string> */
  private function mapped(string $cep, string $line): array {
    [, $street, $district, $city, $state] = array_pad(explode("\t", $line), 6, '');
    return [
      'postal_code' => $cep,
      'address_line1' => $street,
      'dependent_locality' => $district,
      'locality' => $city,
      'administrative_area' => $state,
    ];
  }

}

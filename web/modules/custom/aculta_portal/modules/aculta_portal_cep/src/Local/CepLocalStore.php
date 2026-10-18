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

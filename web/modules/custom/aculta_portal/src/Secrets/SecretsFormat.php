<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Secrets;

/**
 * Formato do arquivo de credenciais (ACULTA Secrets Contract), sem I/O.
 *
 * Duplica de propósito a lógica da ferramenta de deploy: nenhum dos dois depende do outro.
 * Os nomes vêm de config/secrets-contract.json.
 */
final class SecretsFormat {

  private const NAME_PATTERN = '/^[A-Z][A-Z0-9_]*$/';

  /**
   * Lê NAME=value. Ignora linhas vazias e comentários (#). O valor é literal (pode conter "=").
   *
   * @return array<string, string>|null null se alguma linha não tem essa forma.
   */
  public static function parse(string $content): ?array {
    $values = [];
    foreach (preg_split('/\R/', $content) ?: [] as $line) {
      $trim = trim($line);
      if ($trim === '' || str_starts_with($trim, '#')) {
        continue;
      }
      $pos = strpos($line, '=');
      if ($pos === FALSE) {
        return NULL;
      }
      $name = substr($line, 0, $pos);
      if (preg_match(self::NAME_PATTERN, $name) !== 1) {
        return NULL;
      }
      $values[$name] = substr($line, $pos + 1);
    }
    return $values;
  }

  /**
   * @param array<string, mixed> $contract
   * @return string[]
   */
  public static function allowedNames(array $contract): array {
    return array_values(array_map(static fn(array $v): string => (string) $v['name'], $contract['variables'] ?? []));
  }

  /**
   * @param array<string, mixed> $contract
   * @return string[]
   */
  public static function requiredNames(array $contract, string $environment): array {
    $names = [];
    foreach ($contract['variables'] ?? [] as $variable) {
      if (in_array($environment, (array) ($variable['required_in'] ?? []), TRUE)) {
        $names[] = (string) $variable['name'];
      }
    }
    return $names;
  }

  /**
   * Regra de permissão do arquivo de credenciais (alinhada ao loader do Drupal):
   * recusa escrita de grupo (0020) e qualquer acesso de outros (0007). Leitura de grupo é
   * permitida, porque o processo web lê o arquivo por ACL de leitura.
   */
  public static function modeProblem(int $perms): ?string {
    if (($perms & 0020) !== 0) {
      return 'escrita de grupo (exigido: sem escrita para grupo e sem acesso para outros)';
    }
    return ($perms & 0007) !== 0 ? 'acesso de outros (exigido: sem acesso para outros)' : NULL;
  }

}

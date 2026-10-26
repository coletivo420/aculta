<?php

declare(strict_types=1);

namespace Drupal\aculta_patcher;

/**
 * Regras administrativas de um registro de patch: pacote, versão exata e diff.
 *
 * Classe pura. Devolve códigos de erro por campo; o formulário traduz cada código para a mensagem exibida.
 */
final class PatchFormRules {

  /**
   * Limite do diff no formulário, igual ao do exportador.
   */
  public const MAX_BYTES = PatchDiffValidator::MAX_BYTES;

  /**
   * Erros por campo. Vazio quando o registro pode ser salvo.
   *
   * Códigos: `package_invalid`, `version_not_exact`, `patch_too_large`, `patch_not_unified`, `patch_path_escapes`.
   *
   * @return array<string, string>
   *   Código de erro indexado pelo nome do campo.
   */
  public function errors(string $package, string $version, string $diff): array {
    $errors = [];
    if (preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', trim($package)) !== 1) {
      $errors['package'] = 'package_invalid';
    }
    if (preg_match('/^[A-Za-z0-9.+_-]+$/', trim($version)) !== 1) {
      $errors['version'] = 'version_not_exact';
    }
    $diff = trim($diff);
    if (strlen($diff) > self::MAX_BYTES) {
      $errors['patch'] = 'patch_too_large';
    }
    elseif (!str_contains($diff, '--- ') || !str_contains($diff, '+++ ') || preg_match('/^@@/m', $diff) !== 1) {
      $errors['patch'] = 'patch_not_unified';
    }
    elseif ((new PatchDiffValidator())->escapesPackage($diff)) {
      $errors['patch'] = 'patch_path_escapes';
    }
    return $errors;
  }

}

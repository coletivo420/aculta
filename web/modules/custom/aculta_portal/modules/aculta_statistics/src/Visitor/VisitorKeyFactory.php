<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Visitor;

use Drupal\Core\Site\Settings;

/**
 * Cria o identificador de visitante com o salt do site (settings.php).
 *
 * É uma fábrica porque o salt só existe no bootstrap do site; testes criam
 * `VisitorKey` diretamente com um salt de teste.
 */
final class VisitorKeyFactory {

  /**
   * Cria o identificador com o `hash_salt` do ambiente.
   */
  public static function create(): VisitorKey {
    return new VisitorKey(Settings::getHashSalt());
  }

}

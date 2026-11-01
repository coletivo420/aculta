<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_agenda\Calendar;

use Drupal\Core\State\StateInterface;

/**
 * Token revogável do .ics pessoal. Guarda só o hash do token, nunca o valor.
 *
 * One active token is allowed per user. Generating replaces it; revoking
 * deletes it and immediately invalidates the old link.
 */
final class PersonalCalendarTokens {

  /**
   * Chave de estado: uid => hash do token ativo.
   */
  public const ESTADO = 'aculta_lms_agenda.tokens';

  /**
   * Construtor.
   */
  public function __construct(
    private readonly StateInterface $estado,
  ) {}

  /**
   * Generates a token, replacing the prior value, and returns it once.
   */
  public function gerar(int $uid): string {
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $mapa = $this->mapa();
    $mapa[$uid] = self::hash($token);
    $this->estado->set(self::ESTADO, $mapa);
    return $token;
  }

  /**
   * Revoga o token do usuário. Devolve TRUE se havia um.
   */
  public function revogar(int $uid): bool {
    $mapa = $this->mapa();
    if (!array_key_exists($uid, $mapa)) {
      return FALSE;
    }
    unset($mapa[$uid]);
    $this->estado->set(self::ESTADO, $mapa);
    return TRUE;
  }

  /**
   * Usuário dono do token, ou NULL se o token não existe ou foi revogado.
   */
  public function usuarioDoToken(string $token): ?int {
    if ($token === '') {
      return NULL;
    }
    $hash = self::hash($token);
    foreach ($this->mapa() as $uid => $hashGuardado) {
      if (hash_equals((string) $hashGuardado, $hash)) {
        return (int) $uid;
      }
    }
    return NULL;
  }

  /**
   * Hash SHA-256 do token.
   */
  public static function hash(string $token): string {
    return hash('sha256', $token);
  }

  /**
   * Mapa atual uid => hash.
   *
   * @return array<int, string>
   *   User IDs mapped to token hashes.
   */
  private function mapa(): array {
    $mapa = $this->estado->get(self::ESTADO, []);
    return is_array($mapa) ? $mapa : [];
  }

}

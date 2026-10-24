<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\aculta_messenger\Entity\Aviso as ForumAviso;
use Drupal\user\UserDataInterface;

/**
 * Controles individuais de avisos (ACULTA Messenger, F5.2).
 *
 * Avisos no site são obrigatórios. Preferências controlam entrega de e-mail
 * direta ou por resumo, além dos tipos incluídos. Os dados ficam em user.data.
 */
final class AvisoPreferencias implements AvisoPreferenciasInterface {

  public const EMAIL_OFF = 'off';

  public const EMAIL_DIGEST = 'digest';

  public const EMAIL_IMMEDIATE = 'immediate';

  public const MODULO = 'aculta_messenger';

  public function __construct(
    private readonly UserDataInterface $userData,
  ) {}

  /**
   * Verifica se o resumo diário por e-mail está ligado para esta conta.
   *
   * @param int $uid
   *   ID da conta.
   *
   * @return bool
   *   TRUE quando a conta optou pelo resumo.
   */
  public function resumoEmailLigado(int $uid): bool {
    return (bool) $this->userData->get(self::MODULO, $uid, 'avisos_resumo_email');
  }

  /**
   * Verifica se o tipo está incluído no resumo da conta.
   *
   * @param int $uid
   *   ID da conta.
   * @param string $tipo
   *   Tipo de aviso.
   *
   * @return bool
   *   TRUE quando o tipo está incluído.
   */
  public function tipoNoResumo(int $uid, string $tipo): bool {
    if (in_array($tipo, [ForumAviso::TIPO_RESPOSTA, ForumAviso::TIPO_ATUALIZACAO], TRUE)) {
      return $this->emailMode($uid, $tipo) === self::EMAIL_DIGEST;
    }
    $valor = $this->userData->get(self::MODULO, $uid, 'avisos_resumo_' . $tipo);
    return $valor === NULL ? TRUE : (bool) $valor;
  }

  /**
   * Returns the user's email delivery mode for one notice category.
   *
   * A missing preference defaults to the existing opt-in daily digest path;
   * the global digest switch must still be enabled before digest delivery.
   */
  public function emailMode(int $uid, string $category): string {
    $mode = $this->userData->get(self::MODULO, $uid, 'avisos_email_mode_' . $category);
    if (in_array($mode, [self::EMAIL_OFF, self::EMAIL_DIGEST, self::EMAIL_IMMEDIATE], TRUE)) {
      return $mode;
    }
    // Preserve existing reply/update choices until the person selects a mode.
    if (in_array($category, [ForumAviso::TIPO_RESPOSTA, ForumAviso::TIPO_ATUALIZACAO], TRUE)) {
      $legacy = $this->userData->get(self::MODULO, $uid, 'avisos_resumo_' . $category);
      if ($legacy !== NULL && !(bool) $legacy) {
        return self::EMAIL_OFF;
      }
    }
    return self::EMAIL_DIGEST;
  }

  /**
   * Saves a user's direct/digest/off mode for a notice category.
   *
   * @param int $uid
   *   Account ID.
   * @param string $category
   *   Notice category.
   * @param string $mode
   *   One of the supported email modes.
   */
  public function saveEmailMode(int $uid, string $category, string $mode): void {
    if (!in_array($mode, [self::EMAIL_OFF, self::EMAIL_DIGEST, self::EMAIL_IMMEDIATE], TRUE)) {
      throw new \InvalidArgumentException('Modo de e-mail inválido.');
    }
    $this->userData->set(self::MODULO, $uid, 'avisos_email_mode_' . $category, $mode);
  }

  /**
   * Grava as preferências. Não altera os avisos no site. */
  public function salvar(int $uid, bool $resumoEmail, bool $respostas, bool $atualizacoes): void {
    $this->userData->set(self::MODULO, $uid, 'avisos_resumo_email', $resumoEmail ? 1 : 0);
    $this->userData->set(self::MODULO, $uid, 'avisos_resumo_' . ForumAviso::TIPO_RESPOSTA, $respostas ? 1 : 0);
    $this->userData->set(self::MODULO, $uid, 'avisos_resumo_' . ForumAviso::TIPO_ATUALIZACAO, $atualizacoes ? 1 : 0);
  }

}

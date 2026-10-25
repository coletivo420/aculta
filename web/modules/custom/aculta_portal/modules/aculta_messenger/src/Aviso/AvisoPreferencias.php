<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\aculta_messenger\Entity\Aviso as ForumAviso;
use Drupal\Core\Config\ConfigFactoryInterface;
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

  public const EMAIL_PRIVATE_MESSAGES = 'mensagens_privadas';

  public const EMAIL_FRIENDSHIP = 'amizade';

  /**
   * Categories whose existing email behavior is immediate-only. */
  public const EMAIL_IMMEDIATE_ONLY_CATEGORIES = [
    self::EMAIL_FRIENDSHIP,
  ];

  /**
   * Shared notice categories whose e-mail modes are controlled by the site. */
  public const EMAIL_CATEGORIES = [
    ForumAviso::TIPO_RESPOSTA,
    ForumAviso::TIPO_ATUALIZACAO,
    'aprendizagem',
    'wiki',
    'loja',
    'doacao',
    self::EMAIL_PRIVATE_MESSAGES,
    self::EMAIL_FRIENDSHIP,
  ];

  public function __construct(
    private readonly UserDataInterface $userData,
    private readonly ?ConfigFactoryInterface $configFactory = NULL,
  ) {}

  /**
   * Returns modes allowed by administrators; opting out is always available. */
  public function allowedEmailModes(string $category): array {
    if (!in_array($category, self::EMAIL_CATEGORIES, TRUE)) {
      return [self::EMAIL_OFF];
    }
    $configured = $this->configFactory?->get('aculta_messenger.settings')->get('email_modes.' . $category);
    $allowed = [self::EMAIL_OFF];
    if (in_array($category, self::EMAIL_IMMEDIATE_ONLY_CATEGORIES, TRUE)) {
      if ($configured === NULL) {
        return [...$allowed, self::EMAIL_IMMEDIATE];
      }
      if (!is_array($configured)) {
        return $allowed;
      }
      return ($configured[self::EMAIL_IMMEDIATE] ?? FALSE) === TRUE
        ? [...$allowed, self::EMAIL_IMMEDIATE]
        : $allowed;
    }
    if ($configured === NULL) {
      return [...$allowed, self::EMAIL_DIGEST, self::EMAIL_IMMEDIATE];
    }
    // A malformed explicit policy must never re-enable modes. Missing config
    // keeps the backwards-compatible defaults above; configured values must
    // be actual booleans as required by the config schema.
    if (!is_array($configured)) {
      return $allowed;
    }
    if (($configured[self::EMAIL_DIGEST] ?? FALSE) === TRUE) {
      $allowed[] = self::EMAIL_DIGEST;
    }
    if (($configured[self::EMAIL_IMMEDIATE] ?? FALSE) === TRUE) {
      $allowed[] = self::EMAIL_IMMEDIATE;
    }
    return $allowed;
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
    return $this->emailMode($uid, $tipo) === self::EMAIL_DIGEST;
  }

  /**
   * Returns the user's email delivery mode for one notice category.
   *
   * An explicit category preference is authoritative. Shared notice categories
   * default to site-only delivery when no choice is stored; legacy choices are
   * copied by SharedEmailPreferenceMigration before their source keys are
   * removed. Private messages and friendship retain their own defaults.
   */
  public function emailMode(int $uid, string $category): string {
    if (!in_array($category, self::EMAIL_CATEGORIES, TRUE)) {
      return self::EMAIL_OFF;
    }
    $mode = $this->userData->get(self::MODULO, $uid, 'avisos_email_mode_' . $category);
    if (!in_array($mode, [self::EMAIL_OFF, self::EMAIL_DIGEST, self::EMAIL_IMMEDIATE], TRUE)) {
      if ($category === self::EMAIL_PRIVATE_MESSAGES) {
        $default = $this->configFactory?->get('aculta_messenger.settings')
          ->get('email_modes.' . self::EMAIL_PRIVATE_MESSAGES . '.default_mode');
        $mode = in_array($default, [self::EMAIL_OFF, self::EMAIL_DIGEST, self::EMAIL_IMMEDIATE], TRUE)
          ? $default
          : self::EMAIL_IMMEDIATE;
      }
      elseif ($category === self::EMAIL_FRIENDSHIP) {
        // Friendship email was previously sent by default; preserve that
        // behavior until the member explicitly chooses site-only delivery.
        $mode = self::EMAIL_IMMEDIATE;
      }
      else {
        $mode = self::EMAIL_OFF;
      }
    }
    return in_array($mode, $this->allowedEmailModes($category), TRUE) ? $mode : self::EMAIL_OFF;
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
    if (!in_array($category, self::EMAIL_CATEGORIES, TRUE)
      || !in_array($mode, $this->allowedEmailModes($category), TRUE)) {
      throw new \InvalidArgumentException('Modo de e-mail inválido.');
    }
    $this->userData->set(self::MODULO, $uid, 'avisos_email_mode_' . $category, $mode);
  }

}

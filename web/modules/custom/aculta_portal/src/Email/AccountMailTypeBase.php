<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Base dos textos de conta enviados pelo módulo user do Core e apresentados pela camada (decisão de 2026-10-20).
 *
 * As variáveis vêm de `AccountMailRouter`: name, username, site, login_url e one_time_url. `one_time_url` é um link de
 * uso único: é segredo, por isso não entra em logs nem em textos de erro.
 */
abstract class AccountMailTypeBase implements MailTypeInterface {

  public function __construct(
    protected readonly TranslationInterface $translation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function variables(): array {
    return [
      'name' => ['label' => (string) $this->translation->translate('Nome de exibição'), 'sample' => 'Pessoa Exemplo'],
      'username' => ['label' => (string) $this->translation->translate('Nome de usuário'), 'sample' => 'pessoa'],
      'site' => ['label' => (string) $this->translation->translate('Nome do site'), 'sample' => 'Associação Cultural Antiproibicionista'],
      'login_url' => ['label' => (string) $this->translation->translate('Endereço de entrada'), 'sample' => 'https://example.invalid/user'],
      'one_time_url' => ['label' => (string) $this->translation->translate('Link de uso único (segredo)'), 'sample' => 'https://example.invalid/user/reset/exemplo'],
    ];
  }

}

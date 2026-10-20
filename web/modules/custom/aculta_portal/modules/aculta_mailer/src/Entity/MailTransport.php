<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Transporte de e-mail do ACULTA Mailer (F14, substitui mailer_transport).
 *
 * Não guarda senha: só o nome da credencial no arquivo de segredos (ver MailTransportFactory).
 */
#[ConfigEntityType(
  id: self::ENTITY_TYPE,
  label: new TranslatableMarkup('Transporte de e-mail'),
  label_collection: new TranslatableMarkup('Transportes de e-mail'),
  config_prefix: 'transport',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'list_builder' => 'Drupal\Core\Config\Entity\ConfigEntityListBuilder',
    'route_provider' => [
      'html' => 'Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider',
    ],
  ],
  admin_permission: 'administer aculta_mailer',
  config_export: [
    'id',
    'label',
    'plugin',
    'host',
    'port',
    'user',
    'password_secret',
    'verify_peer',
  ],
)]
final class MailTransport extends ConfigEntityBase {

  public const ENTITY_TYPE = 'aculta_mailer_transport';

  /**
   * Tipo do transporte: smtp (servidor com autenticação) ou brevo (API).
   */
  protected string $plugin = 'smtp';

  /**
   * Servidor SMTP (vazio no transporte por API).
   */
  protected string $host = '';

  /**
   * Porta do servidor.
   */
  protected int $port = 465;

  /**
   * Usuário SMTP (não secreto).
   */
  protected string $user = '';

  /**
   * Nome da variável do arquivo de segredos com a senha (ex.: SMTP_PASSWORD). Vazio sem autenticação.
   */
  protected string $password_secret = '';

  /**
   * Verifica o certificado do servidor.
   */
  protected bool $verify_peer = TRUE;

  /**
   * Tipo do transporte.
   */
  public function plugin(): string {
    return $this->plugin;
  }

  /**
   * Servidor.
   */
  public function host(): string {
    return $this->host;
  }

  /**
   * Porta.
   */
  public function port(): int {
    return $this->port;
  }

  /**
   * Usuário.
   */
  public function user(): string {
    return $this->user;
  }

  /**
   * Nome da credencial da senha.
   */
  public function passwordSecret(): string {
    return $this->password_secret;
  }

  /**
   * Se o certificado é verificado.
   */
  public function verifyPeer(): bool {
    return $this->verify_peer;
  }

}

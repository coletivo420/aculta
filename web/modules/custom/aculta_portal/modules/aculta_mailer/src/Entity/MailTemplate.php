<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Entity;

use Drupal\aculta_mailer\Form\MailTemplateForm;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Modelo de e-mail editável: assunto e corpo para um tipo registrado. ID = ID do tipo.
 */
#[ConfigEntityType(
  id: self::ENTITY_TYPE,
  label: new TranslatableMarkup('Modelo de e-mail'),
  label_collection: new TranslatableMarkup('Modelos de e-mail'),
  config_prefix: 'template',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'status' => 'status',
  ],
  handlers: [
    'list_builder' => MailConfigListBuilder::class,
    'form' => [
      'add' => MailTemplateForm::class,
      'default' => MailTemplateForm::class,
      'edit' => MailTemplateForm::class,
    ],
    'route_provider' => [
      'html' => 'Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider',
    ],
  ],
  links: [
    'collection' => '/painel-administrativo/aculta/mail/modelos',
    'add-form' => '/painel-administrativo/aculta/mail/modelos/add',
    'edit-form' => '/painel-administrativo/aculta/mail/modelos/{aculta_mailer_template}',
  ],
  admin_permission: 'administer aculta_mailer',
  config_export: [
    'id',
    'label',
    'status',
    'layout',
    'subject',
    'body',
    'body_format',
  ],
)]
final class MailTemplate extends ConfigEntityBase {

  /**
   * ID da entidade de modelos.
   */
  public const ENTITY_TYPE = 'aculta_mailer_template';

  /**
   * ID do layout (vazio = sem layout).
   */
  protected string $layout = '';

  /**
   * Assunto, com placeholders.
   */
  protected string $subject = '';

  /**
   * Corpo com placeholders. Texto simples quando o formato está vazio.
   */
  protected string $body = '';

  /**
   * Formato do corpo: vazio (texto simples) ou o formato HTML restrito do editor.
   */
  protected string $body_format = '';

  /**
   * Formato HTML restrito do editor de modelos (criado pelo aculta_editor).
   */
  public const BODY_FORMAT_HTML = 'email_html';

  /**
   * ID do layout, ou vazio.
   */
  public function getLayout(): string {
    return $this->layout;
  }

  /**
   * Define o layout do modelo.
   */
  public function setLayout(string $layout): static {
    $this->layout = $layout;
    return $this;
  }

  /**
   * Assunto do modelo.
   */
  public function getSubject(): string {
    return $this->subject;
  }

  /**
   * Define o assunto do modelo.
   */
  public function setSubject(string $subject): static {
    $this->subject = $subject;
    return $this;
  }

  /**
   * Corpo do modelo.
   */
  public function getBody(): string {
    return $this->body;
  }

  /**
   * Define o corpo do modelo.
   */
  /**
   * Formato do corpo ('' = texto simples).
   */
  public function getBodyFormat(): string {
    return $this->body_format;
  }

  /**
   * Corpo em HTML restrito (formato do editor de modelos).
   */
  public function isHtmlBody(): bool {
    return $this->body_format === self::BODY_FORMAT_HTML;
  }

  /**
   * Grava o formato do corpo.
   */
  public function setBodyFormat(string $format): static {
    $this->body_format = $format;
    return $this;
  }

  public function setBody(string $body): static {
    $this->body = $body;
    return $this;
  }

}

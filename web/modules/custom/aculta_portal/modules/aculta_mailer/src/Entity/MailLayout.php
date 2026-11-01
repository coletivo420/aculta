<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Entity;

use Drupal\aculta_mailer\Form\MailLayoutForm;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Layout de e-mail: cabeçalho e rodapé em torno do corpo do modelo.
 *
 * Usa `{{ content }}` no lugar do corpo e os campos `{{ brand_name }}` e `{{ brand_footer }}` da marca escolhida.
 */
#[ConfigEntityType(
  id: self::ENTITY_TYPE,
  label: new TranslatableMarkup('Layout de e-mail'),
  label_collection: new TranslatableMarkup('Layouts de e-mail'),
  config_prefix: 'layout',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'list_builder' => MailConfigListBuilder::class,
    'form' => [
      'add' => MailLayoutForm::class,
      'default' => MailLayoutForm::class,
      'edit' => MailLayoutForm::class,
    ],
    'route_provider' => [
      'html' => 'Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider',
    ],
  ],
  links: [
    'collection' => '/painel-administrativo/aculta/mail/layouts',
    'add-form' => '/painel-administrativo/aculta/mail/layouts/add',
    'edit-form' => '/painel-administrativo/aculta/mail/layouts/{aculta_mailer_layout}',
  ],
  admin_permission: 'administer aculta_mailer',
  config_export: [
    'id',
    'label',
    'brand',
    'header',
    'footer',
  ],
)]
final class MailLayout extends ConfigEntityBase {

  /**
   * ID da entidade de layouts.
   */
  public const ENTITY_TYPE = 'aculta_mailer_layout';

  /**
   * ID da marca usada pelo layout.
   */
  protected string $brand = '';

  /**
   * Cabeçalho, em texto simples, com placeholders.
   */
  protected string $header = '';

  /**
   * Rodapé, em texto simples, com placeholders.
   */
  protected string $footer = '';

  /**
   * ID da marca usada pelo layout.
   */
  public function getBrand(): string {
    return $this->brand;
  }

  /**
   * Define a marca do layout.
   */
  public function setBrand(string $brand): static {
    $this->brand = $brand;
    return $this;
  }

  /**
   * Cabeçalho do layout.
   */
  public function getHeader(): string {
    return $this->header;
  }

  /**
   * Define o cabeçalho do layout.
   */
  public function setHeader(string $header): static {
    $this->header = $header;
    return $this;
  }

  /**
   * Rodapé do layout.
   */
  public function getFooter(): string {
    return $this->footer;
  }

  /**
   * Define o rodapé do layout.
   */
  public function setFooter(string $footer): static {
    $this->footer = $footer;
    return $this;
  }

}

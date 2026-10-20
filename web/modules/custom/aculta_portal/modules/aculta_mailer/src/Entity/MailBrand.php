<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Entity;

use Drupal\aculta_mailer\Form\MailBrandForm;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Identidade de envio: nome e rodapé institucional, usados pelos layouts (ver MailLayout).
 */
#[ConfigEntityType(
  id: self::ENTITY_TYPE,
  label: new TranslatableMarkup('Marca de e-mail'),
  label_collection: new TranslatableMarkup('Marcas de e-mail'),
  config_prefix: 'brand',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'list_builder' => 'Drupal\Core\Config\Entity\ConfigEntityListBuilder',
    'form' => [
      'default' => MailBrandForm::class,
      'edit' => MailBrandForm::class,
    ],
    'route_provider' => [
      'html' => 'Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider',
    ],
  ],
  links: [
    'collection' => '/painel-administrativo/configuracoes/aculta/envios-de-email/marcas',
    'edit-form' => '/painel-administrativo/configuracoes/aculta/envios-de-email/marcas/{aculta_mailer_brand}',
  ],
  admin_permission: 'administer aculta_mailer',
  config_export: [
    'id',
    'label',
    'name',
    'footer',
    'logo_fid',
  ],
)]
final class MailBrand extends ConfigEntityBase {

  /**
   * ID da entidade de marcas.
   */
  public const ENTITY_TYPE = 'aculta_mailer_brand';

  /**
   * Nome exibido no cabeçalho.
   */
  protected string $name = '';

  /**
   * Rodapé institucional, em texto simples.
   */
  protected string $footer = '';

  /**
   * ID do arquivo do logo (0 sem logo). Upload pela política do aculta_image.
   */
  protected int $logo_fid = 0;

  /**
   * Nome exibido no cabeçalho.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Define o nome exibido no cabeçalho.
   */
  public function setName(string $name): static {
    $this->name = $name;
    return $this;
  }

  /**
   * ID do arquivo do logo, ou 0.
   */
  public function getLogoFid(): int {
    return $this->logo_fid;
  }

  /**
   * Define o arquivo do logo.
   */
  public function setLogoFid(int $fid): static {
    $this->logo_fid = $fid;
    return $this;
  }

  /**
   * Rodapé institucional.
   */
  public function getFooter(): string {
    return $this->footer;
  }

  /**
   * Define o rodapé institucional.
   */
  public function setFooter(string $footer): static {
    $this->footer = $footer;
    return $this;
  }

}

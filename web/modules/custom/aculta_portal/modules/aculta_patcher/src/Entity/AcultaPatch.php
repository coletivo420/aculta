<?php

declare(strict_types=1);

namespace Drupal\aculta_patcher\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Stores one Composer patch record for a later build.
 */
#[ConfigEntityType(
  id: 'aculta_patch',
  config_prefix: 'patch',
  label: new TranslatableMarkup('Patch ACULTA'),
  label_collection: new TranslatableMarkup('Patches e snippets'),
  handlers: [
    'list_builder' => 'Drupal\\aculta_patcher\\Entity\\AcultaPatchListBuilder',
    'form' => [
      'default' => 'Drupal\\aculta_patcher\\Form\\AcultaPatchForm',
      'delete' => 'Drupal\\Core\\Entity\\EntityDeleteForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\\Core\\Entity\\Routing\\DefaultHtmlRouteProvider',
    ],
  ],
  admin_permission: 'administer aculta patches',
  entity_keys: ['id' => 'id', 'label' => 'label', 'status' => 'status'],
  links: [
    'collection' => '/painel-administrativo/configuracoes/desenvolvimento/patches-aculta',
    'add-form' => '/painel-administrativo/configuracoes/desenvolvimento/patches-aculta/adicionar',
    'edit-form' => '/painel-administrativo/configuracoes/desenvolvimento/patches-aculta/{aculta_patch}',
    'delete-form' => '/painel-administrativo/configuracoes/desenvolvimento/patches-aculta/{aculta_patch}/excluir',
  ],
  config_export: ['id', 'label', 'status', 'package', 'version', 'issue_url', 'patch'],
)]
final class AcultaPatch extends ConfigEntityBase {

  /**
   * The administrative label for this patch.
   */
  protected string $label = '';

  /**
   * The Composer package name receiving this patch.
   */
  protected string $package = '';

  /**
   * The exact Composer package version validated for the patch.
   */
  protected string $version = '';

  /**
   * The upstream issue or reference URL.
   */
  protected string $issue_url = '';

  /**
   * The unified diff content.
   */
  protected string $patch = '';

  /**
   * Gets the package name.
   */
  public function getPackage(): string {
    return $this->package;
  }

  /**
   * Gets the exact package version.
   */
  public function getVersion(): string {
    return $this->version;
  }

  /**
   * Gets the upstream issue URL.
   */
  public function getIssueUrl(): string {
    return $this->issue_url;
  }

  /**
   * Gets the unified diff content.
   */
  public function getPatch(): string {
    return $this->patch;
  }

}

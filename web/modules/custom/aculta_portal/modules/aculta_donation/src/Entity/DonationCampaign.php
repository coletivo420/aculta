<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[ConfigEntityType(
  id: 'aculta_donation_campaign',
  config_prefix: 'campanha',
  label: new TranslatableMarkup('Campanha de doação'),
  label_collection: new TranslatableMarkup('Campanhas de doação'),
  handlers: [
    'list_builder' => 'Drupal\\Core\\Config\\Entity\\ConfigEntityListBuilder',
    'form' => [
      'default' => 'Drupal\\aculta_donation\\Form\\DonationCampaignForm',
      'delete' => 'Drupal\\Core\\Entity\\EntityDeleteForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\\Core\\Entity\\Routing\\DefaultHtmlRouteProvider',
    ],
  ],
  admin_permission: 'administer aculta donation campaigns',
  entity_keys: ['id' => 'id', 'label' => 'label', 'status' => 'status'],
  links: [
    'collection' => '/painel-administrativo/configuracoes/aculta/doacoes/campanhas',
    'add-form' => '/painel-administrativo/configuracoes/aculta/doacoes/campanhas/adicionar',
    'edit-form' => '/painel-administrativo/configuracoes/aculta/doacoes/campanhas/{aculta_donation_campaign}',
    'delete-form' => '/painel-administrativo/configuracoes/aculta/doacoes/campanhas/{aculta_donation_campaign}/excluir',
  ],
  config_export: ['id', 'label', 'status', 'start', 'end', 'amounts', 'customAmount'],
)]
/**
 * Campanha de doação: valores sugeridos e período de vigência (datas no fuso do site, incluídas).
 */
final class DonationCampaign extends ConfigEntityBase {

  protected string $label = '';

  /** Data de início, AAAA-MM-DD. */
  protected string $start = '';

  /** Data de término, AAAA-MM-DD. */
  protected string $end = '';

  /** Valores sugeridos em reais, em ordem de exibição. */
  protected array $amounts = [];

  protected bool $customAmount = TRUE;

  public function getStart(): string {
    return $this->start;
  }

  public function getEnd(): string {
    return $this->end;
  }

  /** @return int[] */
  public function getAmounts(): array {
    return array_map('intval', $this->amounts);
  }

  public function allowsCustomAmount(): bool {
    return $this->customAmount;
  }

  public function setStart(string $start): static {
    $this->start = $start;
    return $this;
  }

  public function setEnd(string $end): static {
    $this->end = $end;
    return $this;
  }

  /** @param int[] $amounts */
  public function setAmounts(array $amounts): static {
    $this->amounts = array_values(array_map('intval', $amounts));
    return $this;
  }

  public function setCustomAmount(bool $permite): static {
    $this->customAmount = $permite;
    return $this;
  }

}

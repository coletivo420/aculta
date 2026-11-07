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
    'collection' => '/painel-administrativo/aculta/doacoes/campanhas',
    'add-form' => '/painel-administrativo/aculta/doacoes/campanhas/adicionar',
    'edit-form' => '/painel-administrativo/aculta/doacoes/campanhas/{aculta_donation_campaign}',
    'delete-form' => '/painel-administrativo/aculta/doacoes/campanhas/{aculta_donation_campaign}/excluir',
  ],
  config_export: ['id', 'label', 'status', 'start', 'end', 'amounts', 'customAmount'],
)]
/**
 * Campanha de doação: valores sugeridos e período de vigência (datas no fuso do site, incluídas).
 */
final class DonationCampaign extends ConfigEntityBase {

  /**
   * Rótulo da campanha.
   *
   * @var string
   */
  protected string $label = '';

  /**
   * Data de início, AAAA-MM-DD. */
  protected string $start = '';

  /**
   * Data de término, AAAA-MM-DD. */
  protected string $end = '';

  /**
   * Valores sugeridos em reais, em ordem de exibição. */
  protected array $amounts = [];

  /**
   * Se valores personalizados são permitidos.
   *
   * @var bool
   */
  protected bool $customAmount = TRUE;

  /**
   * Devolve a data de início da campanha.
   *
   * @return string
   *   Data de início no formato AAAA-MM-DD.
   */
  public function getStart(): string {
    return $this->start;
  }

  /**
   * Devolve a data de término da campanha.
   *
   * @return string
   *   Data de término no formato AAAA-MM-DD.
   */
  public function getEnd(): string {
    return $this->end;
  }

  /**
   * Devolve os valores sugeridos da campanha.
   *
   * @return int[]
   *   Valores sugeridos em reais.
   */
  public function getAmounts(): array {
    return array_map('intval', $this->amounts);
  }

  /**
   * Informa se a campanha permite valores personalizados.
   *
   * @return bool
   *   TRUE se valores personalizados são permitidos.
   */
  public function allowsCustomAmount(): bool {
    return $this->customAmount;
  }

  /**
   * Define a data de início da campanha.
   *
   * @param string $start
   *   Data de início no formato AAAA-MM-DD.
   *
   * @return static
   *   A própria instância.
   */
  public function setStart(string $start): static {
    $this->start = $start;
    return $this;
  }

  /**
   * Define a data de término da campanha.
   *
   * @param string $end
   *   Data de término no formato AAAA-MM-DD.
   *
   * @return static
   *   A própria instância.
   */
  public function setEnd(string $end): static {
    $this->end = $end;
    return $this;
  }

  /**
   * Define os valores sugeridos da campanha.
   *
   * @param int[] $amounts
   *   Valores sugeridos em reais.
   *
   * @return static
   *   A própria instância.
   */
  public function setAmounts(array $amounts): static {
    $this->amounts = array_values(array_map('intval', $amounts));
    return $this;
  }

  /**
   * Define se a campanha permite valores personalizados.
   *
   * @param bool $permite
   *   TRUE para permitir valores personalizados.
   *
   * @return static
   *   A própria instância.
   */
  public function setCustomAmount(bool $permite): static {
    $this->customAmount = $permite;
    return $this;
  }

}

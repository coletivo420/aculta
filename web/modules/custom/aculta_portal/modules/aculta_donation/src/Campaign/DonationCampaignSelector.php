<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Campaign;

use Drupal\aculta_donation\Entity\DonationCampaign;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\aculta_portal\Support\SupportAmountsProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Escolhe a campanha vigente: status ativo e data de hoje entre início e término (inclusive).
 */
final class DonationCampaignSelector implements SupportAmountsProviderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Valores da campanha vigente para a página de apoio (ver SupportAmountsProviderInterface).
   */
  public function valoresSugeridos(): ?array {
    $campanha = $this->vigente();
    if ($campanha === NULL || $campanha->getAmounts() === []) {
      return NULL;
    }
    return ['amounts' => $campanha->getAmounts(), 'customAmount' => $campanha->allowsCustomAmount()];
  }

  /**
   * Campanha vigente agora, ou NULL. Em caso de sobreposição, vence a de início mais recente.
   */
  public function vigente(?\DateTimeImmutable $agora = NULL): ?DonationCampaign {
    $fuso = new \DateTimeZone($this->configFactory->get('system.date')->get('timezone.default') ?: 'America/Sao_Paulo');
    $hoje = ($agora ?? new \DateTimeImmutable('now', $fuso))->setTimezone($fuso)->format('Y-m-d');
    $candidatas = [];
    foreach ($this->entityTypeManager->getStorage('aculta_donation_campaign')->loadByProperties(['status' => TRUE]) as $campanha) {
      if ($campanha instanceof DonationCampaign && $campanha->getStart() <= $hoje && $hoje <= $campanha->getEnd()) {
        $candidatas[] = $campanha;
      }
    }
    if ($candidatas === []) {
      return NULL;
    }
    usort($candidatas, static fn (DonationCampaign $a, DonationCampaign $b): int => strcmp($b->getStart(), $a->getStart()));
    return $candidatas[0];
  }

}

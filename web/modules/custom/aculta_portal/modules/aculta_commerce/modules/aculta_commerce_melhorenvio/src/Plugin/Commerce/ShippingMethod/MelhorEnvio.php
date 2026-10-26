<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Plugin\Commerce\ShippingMethod;

use Drupal\aculta_commerce_melhorenvio\Quote\MelhorEnvioQuoter;
use Drupal\aculta_commerce_melhorenvio\Quote\QuotePackage;
use Drupal\aculta_commerce_melhorenvio\Quote\QuoteRateFactory;
use Drupal\commerce_shipping\Attribute\CommerceShippingMethod;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\physical\LengthUnit;
use Drupal\physical\WeightUnit;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Método de envio com cotação em tempo real no Melhor Envio.
 *
 * Serviços: IDs confirmados na especificação e no sandbox. Origem na configuração do módulo; destino no perfil de
 * envio da remessa. Contratação e etiqueta ficam para as próximas fases (ME-0.3).
 */
#[CommerceShippingMethod(
  id: 'aculta_melhorenvio',
  label: new TranslatableMarkup('Melhor Envio'),
  services: [
    '1' => new TranslatableMarkup('PAC (Correios)'),
    '2' => new TranslatableMarkup('SEDEX (Correios)'),
    '3' => new TranslatableMarkup('.Package (Jadlog)'),
    '4' => new TranslatableMarkup('.Com (Jadlog)'),
    '17' => new TranslatableMarkup('Mini Envios (Correios)'),
  ],
)]
class MelhorEnvio extends ShippingMethodBase {

  /**
   * Serviço de cotação.
   */
  protected MelhorEnvioQuoter $quoter;

  /**
   * Conversão de opções em tarifas.
   */
  protected QuoteRateFactory $rateFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->quoter = $container->get('aculta_commerce_melhorenvio.quoter');
    $instance->rateFactory = $container->get('aculta_commerce_melhorenvio.quote_rate_factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    $profile = $shipment->getShippingProfile();
    $address = $profile && $profile->hasField('address') && !$profile->get('address')->isEmpty()
      ? $profile->get('address')->first()->toArray()
      : NULL;
    $packageType = $shipment->getPackageType() ?? $this->getDefaultPackageType();
    $weight = $shipment->getWeight() ?? $packageType->getWeight();
    $declared = $shipment->getTotalDeclaredValue();

    $package = new QuotePackage(
      widthCm: (float) $packageType->getWidth()->convert(LengthUnit::CENTIMETER)->getNumber(),
      heightCm: (float) $packageType->getHeight()->convert(LengthUnit::CENTIMETER)->getNumber(),
      lengthCm: (float) $packageType->getLength()->convert(LengthUnit::CENTIMETER)->getNumber(),
      weightKg: (float) $weight->convert(WeightUnit::KILOGRAM)->getNumber(),
      insuranceValue: $declared && $declared->getCurrencyCode() === 'BRL' ? (float) $declared->getNumber() : 0.0,
    );

    return $this->rateFactory->rates(
      $this->quoter->quote($address, $package),
      (string) $this->parentEntity->id(),
      array_values(array_map('strval', $this->configuration['services'] ?? [])),
    );
  }

}

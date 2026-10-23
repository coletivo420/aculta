<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Address;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Monta o par origem/destino de uma operação logística a partir do perfil de envio do pedido.
 *
 * Cotação, contratação e etiqueta usam este mesmo caminho, para que o CEP e os campos sejam os mesmos.
 * - Destino: recebido pelo chamador, que deve passar o perfil de envio da remessa (não o cadastro atual do usuário).
 * - Origem: configuração do submódulo (aculta_commerce_melhorenvio.settings:origin), nunca fixada no código.
 *
 * Não grava nada e não faz HTTP.
 */
final class ShipmentAddressBinder {

  public const SETTINGS = 'aculta_commerce_melhorenvio.settings';

  public function __construct(
    private readonly MelhorEnvioAddressMapper $mapper,
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configs,
  ) {}

  /**
   * @param array<string, mixed>|null $destination
   *   Campos do Drupal Address do perfil de envio da remessa (snapshot do pedido). NULL quando o perfil não existe.
   * @param string $purpose
   *   MelhorEnvioAddressMapper::PURPOSE_QUOTE ou PURPOSE_SHIPMENT.
   */
  public function bind(?array $destination, string $purpose): ShipmentAddressPair {
    $origin = $this->configs->get(self::SETTINGS)->get('origin');
    return new ShipmentAddressPair(
      from: $this->mapper->map(is_array($origin) ? $origin : NULL, $purpose),
      to: $this->mapper->map($destination, $purpose),
    );
  }

}

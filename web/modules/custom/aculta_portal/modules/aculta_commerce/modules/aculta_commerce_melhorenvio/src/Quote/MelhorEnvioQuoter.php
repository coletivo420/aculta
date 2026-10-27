<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

use Drupal\aculta_commerce_melhorenvio\Address\MelhorEnvioAddressMapper;
use Drupal\aculta_commerce_melhorenvio\Address\ShipmentAddressBinder;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioClientInterface;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioEndpoints;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Cotação de frete: origem da configuração, destino do perfil de envio, pacote da remessa.
 *
 * Nunca interrompe o checkout: endereço inválido ou falha da API devolvem lista vazia e ficam no log, sem CEP nem
 * valores pessoais.
 */
class MelhorEnvioQuoter {

  /**
   * Canal de log do módulo.
   */
  private readonly LoggerInterface $logger;

  /**
   * Cria o serviço.
   */
  public function __construct(
    private readonly MelhorEnvioClientInterface $client,
    private readonly ShipmentAddressBinder $binder,
    private readonly QuoteRequestBuilder $builder,
    private readonly QuoteResponseParser $parser,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('aculta_commerce_melhorenvio');
  }

  /**
   * Opções de entrega para o destino e o pacote.
   *
   * @param array<string, mixed>|null $destination
   *   Campos do Drupal Address do perfil de envio.
   * @param \Drupal\aculta_commerce_melhorenvio\Quote\QuotePackage $package
   *   Pacote da remessa.
   *
   * @return list<\Drupal\aculta_commerce_melhorenvio\Quote\QuoteOption>
   *   Opções disponíveis; vazia quando não há cotação.
   */
  public function quote(?array $destination, QuotePackage $package): array {
    try {
      return $this->attempt($destination, $package);
    }
    catch (MelhorEnvioException $e) {
      $this->logger->warning('Cotação do Melhor Envio não concluída: {category}.', ['category' => $e->category()]);
      return [];
    }
  }

  /**
   * Cotação que informa a falha ao chamador, para o simulador distinguir "sem opções" de "falhou".
   *
   * @param array<string, mixed>|null $destination
   *   Campos do Drupal Address do destino.
   * @param \Drupal\aculta_commerce_melhorenvio\Quote\QuotePackage $package
   *   Pacote da remessa.
   *
   * @return list<\Drupal\aculta_commerce_melhorenvio\Quote\QuoteOption>
   *   Opções disponíveis; vazia quando a API não devolve serviço válido.
   *
   * @throws \Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioException
   *   Endereço inválido (categoria `invalid_address`), credencial ausente ou falha da API.
   */
  public function attempt(?array $destination, QuotePackage $package): array {
    $pair = $this->binder->bind($destination, MelhorEnvioAddressMapper::PURPOSE_QUOTE);
    if (!$pair->isValid()) {
      throw new MelhorEnvioException('invalid_address', 'Endereço inválido para cotação: ' . QuoteRequestBuilder::describeErrors($pair));
    }
    $response = $this->client->request('POST', MelhorEnvioEndpoints::QUOTE, $this->builder->build($pair, $package), TRUE);
    return $this->parser->parse($response);
  }

}

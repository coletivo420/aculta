<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

use Drupal\aculta_commerce_melhorenvio\Address\ShipmentAddressBinder;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioClientInterface;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioException;
use Drupal\aculta_portal_cep\Lookup\CepLookupInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\physical\LengthUnit;
use Drupal\physical\WeightUnit;
use Psr\Log\LoggerInterface;

/**
 * Simulador de frete por CEP: estados explícitos, cache curto e nenhuma falha que chegue ao carrinho.
 *
 * Ordem das verificações (nenhuma chamada à API antes de todas passarem):
 * 1. CEP de destino válido, pela consulta compartilhada do Portal (aculta_portal_cep.lookup).
 * 2. Origem configurada (aculta_commerce_melhorenvio.settings:origin).
 * 3. Credenciais do ambiente ativo.
 * 4. Pacote real da remessa. Sem pacote, a cotação seria feita sobre dimensões inventadas.
 *
 * Só resultados de API bem-sucedidos vão para o cache, por CEP e pacote, e a tag de configuração invalida o cache ao
 * mudar a origem. Falhas nunca são guardadas.
 */
class ShippingSimulator {

  /**
   * Tempo de vida do cache de cotação, em segundos.
   */
  public const CACHE_TTL = 300;

  /**
   * Tag de cache própria do simulador.
   */
  private const CACHE_TAG = 'aculta_commerce_melhorenvio:quote';

  /**
   * Nome do objeto de configuração com a origem.
   */
  private const SETTINGS = 'aculta_commerce_melhorenvio.settings';

  /**
   * Canal de log do módulo.
   */
  private readonly LoggerInterface $logger;

  /**
   * Cria o serviço.
   */
  public function __construct(
    private readonly MelhorEnvioClientInterface $client,
    private readonly MelhorEnvioQuoter $quoter,
    private readonly ConfigFactoryInterface $configs,
    private readonly CepLookupInterface $cep,
    private readonly CacheBackendInterface $cache,
    private readonly TimeInterface $time,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('aculta_commerce_melhorenvio');
  }

  /**
   * Simula o frete para o CEP informado.
   *
   * @param string $postalCode
   *   CEP digitado pelo visitante, com ou sem máscara.
   * @param \Drupal\aculta_commerce_melhorenvio\Quote\QuotePackage|null $package
   *   Pacote da remessa. NULL usa o pacote padrão cadastrado na configuração; sem ele, o simulador fica indisponível.
   */
  public function simulate(string $postalCode, ?QuotePackage $package = NULL): ShippingSimulation {
    $destination = $this->cep->normalize($postalCode);
    if ($destination === NULL) {
      return ShippingSimulation::unavailable(ShippingSimulation::REASON_INVALID_POSTAL_CODE);
    }
    $reason = $this->configurationReason();
    if ($reason !== NULL) {
      return ShippingSimulation::unavailable($reason);
    }
    $package ??= $this->packageFor();
    if ($package === NULL) {
      return ShippingSimulation::unavailable(ShippingSimulation::REASON_NO_PACKAGE);
    }

    $key = 'aculta_commerce_melhorenvio:quote:' . hash('sha256', $destination . '|' . $this->packageSignature($package));
    $cached = $this->cache->get($key);
    if ($cached !== FALSE && $cached->data instanceof ShippingSimulation) {
      return $cached->data;
    }

    try {
      // País explícito: a regra de cotação do mapeador recusa destino sem BR.
      $options = $this->quoter->attempt(['country_code' => 'BR', 'postal_code' => $destination], $package);
    }
    catch (MelhorEnvioException $e) {
      // Categoria e status apenas: sem CEP, sem corpo de resposta e sem token.
      $this->logger->warning('Simulação de frete não concluída: {category}.', ['category' => $e->category()]);
      return ShippingSimulation::unavailable(ShippingSimulation::REASON_FAILURE);
    }

    $result = $options === []
      ? new ShippingSimulation(ShippingSimulation::STATE_EMPTY)
      : new ShippingSimulation(ShippingSimulation::STATE_AVAILABLE, NULL, $options);
    $this->cache->set($key, $result, $this->time->getRequestTime() + self::CACHE_TTL, [self::CACHE_TAG, 'config:' . self::SETTINGS]);
    return $result;
  }

  /**
   * Disponibilidade para exibir ao visitante sem CEP: configuração e pacote padrão. Não chama a API.
   */
  public function availabilityReason(?ProductVariationInterface $variation = NULL): ?string {
    if ($this->configurationReason() !== NULL) {
      return $this->configurationReason();
    }
    if ($variation !== NULL) {
      return $this->packageForVariation($variation) === NULL ? ShippingSimulation::REASON_NO_PACKAGE : NULL;
    }
    return $this->packageFor() === NULL ? ShippingSimulation::REASON_NO_PACKAGE : NULL;
  }

  /**
   * Simula o frete de um produto, com o pacote da variação (e não o da configuração).
   *
   * Variação sem peso ou dimensões cadastrados é indisponível (sem pacote), sem chamada à API. CEP inválido continua
   * sendo recusado antes.
   */
  public function simulateForVariation(string $postalCode, ProductVariationInterface $variation): ShippingSimulation {
    $package = $this->packageForVariation($variation);
    if ($package === NULL) {
      if ($this->cep->normalize($postalCode) === NULL) {
        return ShippingSimulation::unavailable(ShippingSimulation::REASON_INVALID_POSTAL_CODE);
      }
      return ShippingSimulation::unavailable(ShippingSimulation::REASON_NO_PACKAGE);
    }
    return $this->simulate($postalCode, $package);
  }

  /**
   * Pacote de uma variação, com regra de consolidação: cada medida é a da variação quando cadastrada, e nunca fica
   * abaixo do padrão. Medida não cadastrada usa o padrão. Sem padrão configurado e sem medida da variação, não há
   * pacote (NULL): nenhuma medida é presumida.
   */
  public function packageForVariation(ProductVariationInterface $variation): ?QuotePackage {
    $minimo = $this->packageFor();
    $width = $height = $length = $kg = 0.0;
    if ($variation->hasField('dimensions') && !$variation->get('dimensions')->isEmpty()) {
      $item = $variation->get('dimensions')->first();
      $width = (float) $item->getWidth()->convert(LengthUnit::CENTIMETER)->getNumber();
      $height = (float) $item->getHeight()->convert(LengthUnit::CENTIMETER)->getNumber();
      $length = (float) $item->getLength()->convert(LengthUnit::CENTIMETER)->getNumber();
    }
    if ($variation->hasField('weight') && !$variation->get('weight')->isEmpty()) {
      $kg = (float) $variation->get('weight')->first()->toMeasurement()->convert(WeightUnit::KILOGRAM)->getNumber();
    }
    $dimensao = fn (float $valor, ?float $padrao): ?float => max($valor > 0 ? $valor : 0.0, $padrao ?? 0.0) ?: NULL;
    $largura = $dimensao($width, $minimo?->widthCm);
    $altura = $dimensao($height, $minimo?->heightCm);
    $comprimento = $dimensao($length, $minimo?->lengthCm);
    $peso = max($kg > 0 ? $kg : 0.0, $minimo?->weightKg ?? 0.0) ?: NULL;
    if ($largura === NULL || $altura === NULL || $comprimento === NULL || $peso === NULL) {
      return NULL;
    }
    return new QuotePackage(
      widthCm: $largura,
      heightCm: $altura,
      lengthCm: $comprimento,
      weightKg: $peso,
      insuranceValue: 0.0,
    );
  }

  /**
   * Simula o frete do carrinho a partir das linhas (variação, quantidade e valor). Cada variação entra com o próprio
   * pacote (padrão como mínimo); variação sem pacote deixa o carrinho indisponível, sem chamar a API.
   *
   * @param list<array{variation: \Drupal\commerce_product\Entity\ProductVariationInterface, quantity: int, total: float}> $lines
   */
  public function simulateForCartLines(string $postalCode, array $lines): ShippingSimulation {
    $items = [];
    foreach ($lines as $line) {
      $package = $this->packageForVariation($line['variation']);
      if ($package === NULL) {
        return ShippingSimulation::unavailable(ShippingSimulation::REASON_NO_PACKAGE);
      }
      $items[] = new QuoteItem((string) $line['variation']->getSku(), $package, $line['quantity'], $line['total']);
    }
    return $this->simulateForItems($postalCode, $items);
  }

  /**
   * Simula o frete de um carrinho: cada item vai à API com a própria medida e quantidade (a API monta as caixas).
   *
   * @param list<\Drupal\aculta_commerce_melhorenvio\Quote\QuoteItem> $items
   *   Itens do carrinho, já com o pacote de cada variação.
   */
  public function simulateForItems(string $postalCode, array $items): ShippingSimulation {
    $destination = $this->cep->normalize($postalCode);
    if ($destination === NULL) {
      return ShippingSimulation::unavailable(ShippingSimulation::REASON_INVALID_POSTAL_CODE);
    }
    $reason = $this->configurationReason();
    if ($reason !== NULL) {
      return ShippingSimulation::unavailable($reason);
    }
    if ($items === []) {
      return ShippingSimulation::unavailable(ShippingSimulation::REASON_EMPTY_CART);
    }
    $assinatura = implode(';', array_map(static fn (QuoteItem $i): string => implode('|', [
      $i->reference, $i->quantity, $i->package->widthCm, $i->package->heightCm,
      $i->package->lengthCm, $i->package->weightKg, $i->insuranceValue,
    ]), $items));
    $key = 'aculta_commerce_melhorenvio:cart:' . hash('sha256', $destination . '|' . $assinatura);
    $cached = $this->cache->get($key);
    if ($cached !== FALSE && $cached->data instanceof ShippingSimulation) {
      return $cached->data;
    }
    try {
      $options = $this->quoter->attemptItems(['country_code' => 'BR', 'postal_code' => $destination], $items);
    }
    catch (MelhorEnvioException $e) {
      $this->logger->warning('Simulação de frete do carrinho não concluída: {category}.', ['category' => $e->category()]);
      return ShippingSimulation::unavailable(ShippingSimulation::REASON_FAILURE);
    }
    $result = $options === []
      ? new ShippingSimulation(ShippingSimulation::STATE_EMPTY)
      : new ShippingSimulation(ShippingSimulation::STATE_AVAILABLE, NULL, $options);
    $this->cache->set($key, $result, $this->time->getRequestTime() + self::CACHE_TTL, [self::CACHE_TAG, 'config:' . self::SETTINGS]);
    return $result;
  }

  /**
   * Pacote padrão da configuração, ou NULL quando algum valor não foi cadastrado (zero ou ausente). Não usa valor padrão
   * inventado: sem medidas reais, não há cotação.
   */
  public function packageFor(): ?QuotePackage {
    $package = $this->configs->get(self::SETTINGS)->get('package');
    if (!is_array($package)) {
      return NULL;
    }
    $width = (int) ($package['width'] ?? 0);
    $height = (int) ($package['height'] ?? 0);
    $length = (int) ($package['length'] ?? 0);
    $weight = (float) ($package['weight'] ?? 0);
    if ($width <= 0 || $height <= 0 || $length <= 0 || $weight <= 0) {
      return NULL;
    }
    return new QuotePackage(
      widthCm: (float) $width,
      heightCm: (float) $height,
      lengthCm: (float) $length,
      weightKg: $weight,
      insuranceValue: 0.0,
    );
  }

  /**
   * Motivo pelo qual a cotação não pode ocorrer agora, ou NULL quando a configuração permite. Não chama a API.
   */
  public function configurationReason(): ?string {
    $origin = $this->configs->get(self::SETTINGS)->get('origin');
    $originPostal = is_array($origin) ? (string) ($origin['postal_code'] ?? '') : '';
    if ($originPostal === '' || $this->cep->normalize($originPostal) === NULL) {
      return ShippingSimulation::REASON_NO_ORIGIN;
    }
    if (!$this->client->hasCredentials()) {
      return ShippingSimulation::REASON_NO_CREDENTIALS;
    }
    return NULL;
  }

  /**
   * Assinatura estável do pacote, para compor a chave de cache.
   */
  private function packageSignature(QuotePackage $package): string {
    return implode('|', [
      $package->widthCm,
      $package->heightCm,
      $package->lengthCm,
      $package->weightKg,
      $package->insuranceValue,
    ]);
  }

}

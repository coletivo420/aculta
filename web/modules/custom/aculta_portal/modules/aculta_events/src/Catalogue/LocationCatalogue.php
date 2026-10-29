<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Catalogue;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Lista pública de locais (/locais), com filtro por tipo (F4).
 *
 * Privacidade (MODELO-DE-DADOS.md): local com precisão "aproximado" mostra
 * só bairro e município; a rua e o CEP não saem. Endereço completo só para
 * local exato.
 */
final class LocationCatalogue {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?AccountInterface $currentUser = NULL,
  ) {}

  /**
   * Locais, opcionalmente de um tipo, em ordem alfabética.
   *
   * @return array<int, array{local: \Drupal\aculta_events\Entity\EventLocation, endereco: string}>
   *   Lista de locais com seus respectivos endereços formatados.
   */
  public function busca(?int $tipo = NULL, ?AccountInterface $account = NULL): array {
    $res = $this->buscaPaginada($tipo, NULL, 0, 0, $account);
    return $res['items'];
  }

  /**
   * Busca locais com filtros e paginação (LOC-05).
   *
   * @param int|null $tipo
   *   Filtro por ID do termo de tipo de local.
   * @param string|null $termo
   *   Filtro de busca textual por nome do local.
   * @param int $page
   *   Número da página (0-indexed).
   * @param int $limit
   *   Quantidade máxima por página (0 para sem limite).
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Conta para verificação de acesso.
   *
   * @return array{items: array<int, array{local: \Drupal\aculta_events\Entity\EventLocation, endereco: string}>, total: int}
   *   Itens paginados e total de registros válidos.
   */
  public function buscaPaginada(
    ?int $tipo = NULL,
    ?string $termo = NULL,
    int $page = 0,
    int $limit = 12,
    ?AccountInterface $account = NULL,
  ): array {
    $conta = $account ?? $this->currentUser;
    $storage = $this->entityTypeManager->getStorage('aculta_event_location');
    $consulta = $storage->getQuery()->sort('title', 'ASC')->accessCheck(FALSE);

    if ($tipo !== NULL && $tipo > 0) {
      $consulta->condition('field_tipo.target_id', $tipo);
    }
    if ($termo !== NULL && trim($termo) !== '') {
      $consulta->condition('title', '%' . trim($termo) . '%', 'LIKE');
    }

    $allIds = $consulta->execute();
    if (empty($allIds)) {
      return ['items' => [], 'total' => 0];
    }

    /** @var \Drupal\aculta_events\Entity\EventLocation[] $locais */
    $locais = $storage->loadMultiple($allIds);
    $validos = [];

    foreach ($locais as $local) {
      if ($local instanceof EventLocation && ($conta === NULL || $local->access('view', $conta))) {
        $validos[] = [
          'local' => $local,
          'endereco' => $this->enderecoPublico($local),
        ];
      }
    }

    $total = count($validos);
    $items = $limit > 0 ? array_slice($validos, $page * $limit, $limit) : $validos;

    return [
      'items' => $items,
      'total' => $total,
    ];
  }

  /**
   * Endereço para exibição, conforme a precisão do local.
   */
  public function enderecoPublico(EventLocation $local): string {
    $item = $local->get('field_endereco')->first();
    if ($item === NULL) {
      return '';
    }
    $e = $item->getValue();
    $bairroMunicipio = array_filter([
      (string) ($e['dependent_locality'] ?? ''),
      (string) ($e['locality'] ?? ''),
      (string) ($e['administrative_area'] ?? ''),
    ]);
    if ((string) $local->get('field_precisao')->value === 'aproximado') {
      return implode(' — ', $bairroMunicipio);
    }
    $cep = preg_replace('/\D/', '', (string) ($e['postal_code'] ?? ''));
    $cepFormatado = strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : $cep;
    $partes = array_filter([
      (string) ($e['address_line1'] ?? ''),
      (string) ($e['address_line2'] ?? ''),
      implode(' — ', $bairroMunicipio),
      $cepFormatado !== '' ? 'CEP ' . $cepFormatado : '',
    ]);
    return implode(', ', $partes);
  }

}

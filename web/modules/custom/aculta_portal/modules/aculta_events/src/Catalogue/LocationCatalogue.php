<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Catalogue;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;

/**
 * Lista pública de locais (/locais), com filtro por tipo (F4).
 *
 * Privacidade (MODELO-DE-DADOS.md): local com precisão "aproximado" mostra
 * só bairro e município; a rua e o CEP não saem. Endereço completo só para
 * local exato.
 */
final class LocationCatalogue {

  /**
   * Número máximo de entidades carregadas por lote durante a busca.
   */
  private const BATCH_SIZE = 100;

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
   * @return array
   *   Itens paginados, total de registros válidos e `cache_metadata`
   *   (\Drupal\Core\Cache\CacheableMetadata) agregado das decisões de acesso.
   */
  public function buscaPaginada(
    ?int $tipo = NULL,
    ?string $termo = NULL,
    int $page = 0,
    int $limit = 12,
    ?AccountInterface $account = NULL,
  ): array {
    // Falha fechado quando o serviço for usado sem conta explícita nem contexto
    // de requisição: locais privados/rascunhos não entram por falta de ACL.
    $conta = $account ?? $this->currentUser ?? new AnonymousUserSession();
    $storage = $this->entityTypeManager->getStorage('aculta_event_location');
    $cacheability = (new CacheableMetadata())
      ->addCacheTags(['aculta_event_location_list']);
    $page = max(0, $page);
    $limit = max(0, $limit);
    $offsetAcessivel = $page * $limit;
    $fimDaPagina = $limit > 0 ? $offsetAcessivel + $limit : PHP_INT_MAX;
    $termo = $termo !== NULL ? trim($termo) : NULL;
    $total = 0;
    $items = [];
    $offsetConsulta = 0;

    do {
      $consulta = $storage->getQuery()
        ->sort('title', 'ASC')
        ->sort('id', 'ASC')
        ->accessCheck(FALSE);
      if ($tipo !== NULL && $tipo > 0) {
        $consulta->condition('field_tipo.target_id', $tipo);
      }
      if ($termo !== NULL && $termo !== '') {
        $consulta->condition('title', '%' . $termo . '%', 'LIKE');
      }
      $ids = $consulta->range($offsetConsulta, self::BATCH_SIZE)->execute();
      if ($ids === []) {
        break;
      }

      /** @var \Drupal\aculta_events\Entity\EventLocation[] $locais */
      $locais = $storage->loadMultiple($ids);
      foreach ($locais as $local) {
        $viewAccess = $local->access('view', $conta, TRUE);
        // Both allowed and denied results affect this list. Bubble their cache
        // contexts so private drafts cannot leak through a shared page cache.
        $cacheability->addCacheableDependency($viewAccess);
        if (!$viewAccess->isAllowed()) {
          continue;
        }
        $cacheability->addCacheableDependency($local);
        if ($total >= $offsetAcessivel && $total < $fimDaPagina) {
          $items[] = [
            'local' => $local,
            'endereco' => $this->enderecoPublico($local),
          ];
        }
        $total++;
      }

      $offsetConsulta += count($ids);
    } while (count($ids) === self::BATCH_SIZE);

    return [
      'items' => $items,
      'total' => $total,
      'cache_metadata' => $cacheability,
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

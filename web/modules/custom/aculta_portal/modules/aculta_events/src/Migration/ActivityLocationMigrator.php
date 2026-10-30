<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Migration;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Serviço de migração idempotente e reversível de endereços legados de Activity para Locais.
 */
final class ActivityLocationMigrator {

  use StringTranslationTrait;

  public const STATE_KEY = 'aculta_events.migrated_activity_locations';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly StateInterface $state,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Identifica atividades legadas que possuem dados de endereço e ainda não referenciam um Local.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Lista de nós elegíveis para migração.
   */
  public function getLegacyCandidates(): array {
    if (!$this->entityTypeManager->hasDefinition('node')) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'activity')
      ->notExists('field_event_location.target_id')
      ->sort('nid', 'ASC');

    $nids = $query->execute();
    if ($nids === []) {
      return [];
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($nids);
    $candidates = [];

    foreach ($nodes as $node) {
      if ($this->hasLegacyAddressData($node)) {
        $candidates[] = $node;
      }
    }

    return $candidates;
  }

  /**
   * Verifica se o nó possui algum dado legado de endereço preenchido.
   */
  public function hasLegacyAddressData(NodeInterface $node): bool {
    foreach ([
      'field_place_name',
      'field_event_street',
      'field_event_postal',
      'field_event_city',
      'field_event_region',
    ] as $field) {
      if ($node->hasField($field) && !$node->get($field)->isEmpty()) {
        $val = trim((string) $node->get($field)->value);
        if ($val !== '') {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Realiza auditoria e diagnóstico prévio das atividades candidatas à migração.
   *
   * @return array<string, mixed>
   *   Métricas e resumo de integridade.
   */
  public function inspect(): array {
    $totalActivities = 0;
    $alreadyLinked = 0;

    if ($this->entityTypeManager->hasDefinition('node')) {
      $storage = $this->entityTypeManager->getStorage('node');
      $totalActivities = (int) $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'activity')
        ->count()
        ->execute();

      $alreadyLinked = (int) $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'activity')
        ->exists('field_event_location.target_id')
        ->count()
        ->execute();
    }

    $candidates = $this->getLegacyCandidates();
    $incomplete = [];
    $dedupeGroups = [];

    foreach ($candidates as $node) {
      $placeName = trim((string) ($node->hasField('field_place_name') ? $node->get('field_place_name')->value : ''));
      $postal = trim((string) ($node->hasField('field_event_postal') ? $node->get('field_event_postal')->value : ''));

      $missing = [];
      if ($placeName === '') {
        $missing[] = 'field_place_name';
      }
      if ($postal === '') {
        $missing[] = 'field_event_postal';
      }

      if ($missing !== []) {
        $incomplete[] = [
          'nid' => (int) $node->id(),
          'title' => (string) $node->label(),
          'missing' => $missing,
        ];
      }

      $dedupeKey = $this->buildDedupeKey($placeName, $postal);
      $dedupeGroups[$dedupeKey][] = (int) $node->id();
    }

    return [
      'total_activities' => $totalActivities,
      'already_linked' => $alreadyLinked,
      'candidates_count' => count($candidates),
      'incomplete_count' => count($incomplete),
      'incomplete_list' => $incomplete,
      'unique_potential_locations' => count($dedupeGroups),
    ];
  }

  /**
   * Executa a migração em lote ou ensaio (dry-run).
   *
   * @param bool $dryRun
   *   Se TRUE, apenas simula e retorna o plano de ação sem persistir alterações.
   *
   * @return array<string, mixed>
   *   Relatório detalhado do processo de migração.
   */
  public function migrate(bool $dryRun = FALSE): array {
    $candidates = $this->getLegacyCandidates();
    $provenance = $this->state->get(self::STATE_KEY, []);

    $createdCount = 0;
    $reusedCount = 0;
    $updatedNodes = 0;
    $createdLocations = [];
    $locationMap = [];

    // Carrega locais existentes para reaproveitamento por chave normalizada (nome + CEP).
    if (!$dryRun && $this->entityTypeManager->hasDefinition('aculta_event_location')) {
      $existingLocations = $this->entityTypeManager->getStorage('aculta_event_location')->loadMultiple();
      foreach ($existingLocations as $existing) {
        if ($existing instanceof EventLocation) {
          $name = trim((string) $existing->label());
          $cep = '';
          if ($existing->hasField('field_endereco') && !$existing->get('field_endereco')->isEmpty()) {
            $cep = trim((string) $existing->get('field_endereco')->first()?->postal_code);
          }
          $key = $this->buildDedupeKey($name, $cep);
          $locationMap[$key] = (int) $existing->id();
        }
      }
    }

    foreach ($candidates as $node) {
      $placeName = trim((string) ($node->hasField('field_place_name') ? $node->get('field_place_name')->value : ''));
      $street = trim((string) ($node->hasField('field_event_street') ? $node->get('field_event_street')->value : ''));
      $postal = trim((string) ($node->hasField('field_event_postal') ? $node->get('field_event_postal')->value : ''));
      $city = trim((string) ($node->hasField('field_event_city') ? $node->get('field_event_city')->value : ''));
      $region = trim((string) ($node->hasField('field_event_region') ? $node->get('field_event_region')->value : ''));

      $locationTitle = $placeName !== '' ? $placeName : (string) $this->t('Local de @title', ['@title' => $node->label()]);
      $dedupeKey = $this->buildDedupeKey($locationTitle, $postal);

      $locationId = NULL;

      if (isset($locationMap[$dedupeKey])) {
        $locationId = $locationMap[$dedupeKey];
        $reusedCount++;
      }
      else {
        if (!$dryRun) {
          $isPublished = (bool) $node->isPublished();
          $status = $isPublished ? 'publicado' : 'rascunho';

          $location = EventLocation::create([
            'title' => $locationTitle,
            'field_status' => $status,
            'field_precisao' => 'exato',
            'field_organizador' => (int) $node->getOwnerId(),
            'field_endereco' => [
              'country_code' => 'BR',
              'address_line1' => $street,
              'dependent_locality' => '',
              'locality' => $city,
              'administrative_area' => strtoupper($region),
              'postal_code' => $postal,
            ],
          ]);
          $location->save();

          $locationId = (int) $location->id();
          $locationMap[$dedupeKey] = $locationId;
          $createdLocations[] = $locationId;
          $provenance[$locationId] = $provenance[$locationId] ?? [];
        }
        $createdCount++;
      }

      if (!$dryRun && $locationId !== NULL) {
        $provenance[$locationId][] = (int) $node->id();
        $provenance[$locationId] = array_values(array_unique($provenance[$locationId]));

        $node->set('field_event_location', $locationId);
        $node->save();
        $updatedNodes++;
      }
    }

    if (!$dryRun) {
      $this->state->set(self::STATE_KEY, $provenance);
      $this->logger->info('Migração de locais de Activity concluída: @created criados, @reused reaproveitados, @nodes nós atualizados.', [
        '@created' => $createdCount,
        '@reused' => $reusedCount,
        '@nodes' => $updatedNodes,
      ]);
    }

    return [
      'dry_run' => $dryRun,
      'candidates_total' => count($candidates),
      'locations_created' => $createdCount,
      'locations_reused' => $reusedCount,
      'nodes_updated' => $dryRun ? count($candidates) : $updatedNodes,
      'created_location_ids' => $createdLocations,
    ];
  }

  /**
   * Executa o rollback idempotente das entidades de Local criadas pela migração.
   *
   * @return array<string, int>
   *   Contagem de nós desvinculados e locais removidos.
   */
  public function rollback(): array {
    $provenance = $this->state->get(self::STATE_KEY, []);
    if ($provenance === []) {
      return [
        'nodes_unlinked' => 0,
        'locations_deleted' => 0,
      ];
    }

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $locationStorage = $this->entityTypeManager->getStorage('aculta_event_location');

    $nodesUnlinked = 0;
    $locationsDeleted = 0;

    foreach ($provenance as $locationId => $nodeIds) {
      $locationId = (int) $locationId;
      if ($nodeIds !== []) {
        $nodes = $nodeStorage->loadMultiple($nodeIds);
        foreach ($nodes as $node) {
          if ($node instanceof NodeInterface && $node->hasField('field_event_location')) {
            $node->set('field_event_location', NULL);
            $node->save();
            $nodesUnlinked++;
          }
        }
      }

      $location = $locationStorage->load($locationId);
      if ($location instanceof EventLocation) {
        $location->delete();
        $locationsDeleted++;
      }
    }

    $this->state->delete(self::STATE_KEY);
    $this->logger->info('Rollback da migração de locais concluído: @nodes nós desvinculados, @locations locais removidos.', [
      '@nodes' => $nodesUnlinked,
      '@locations' => $locationsDeleted,
    ]);

    return [
      'nodes_unlinked' => $nodesUnlinked,
      'locations_deleted' => $locationsDeleted,
    ];
  }

  /**
   * Constrói chave de deduplicação normalizada por nome de local e CEP.
   */
  private function buildDedupeKey(string $placeName, string $postal): string {
    $normName = mb_strtolower(preg_replace('/\s+/', ' ', trim($placeName)));
    $normPostal = preg_replace('/\D/', '', $postal);
    return $normName . '|' . $normPostal;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\aculta_events\Cache\EventCacheTags;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Occurrence\UpcomingDates;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;
use Drupal\views\ViewExecutable;

/**
 * Oculta datas passadas sem cron de invalidação (auditoria, T5).
 *
 * Reimplementa `viewsPostExecute`: Views com base `aculta_event_date` removem
 * linhas vencidas, atualizam `total_rows` e expiram na próxima ocorrência.
 * Nenhuma tarefa agendada é necessária.
 *
 * A tag de lista invalida resultados ao criar, editar ou excluir evento, data
 * ou exceção, sem congelar o max-age da próxima ocorrência.
 *
 * Limite: a remoção ocorre após a consulta. Os rows e `total_rows` permanecem
 * coerentes. Views com período fixo devem filtrar na consulta; este hook cobre
 * somente mudanças dependentes do relógio.
 */
final class EventDateHooks {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly TimeInterface $tempo,
    private readonly UpcomingDates $proximas,
    private readonly AdminContext $adminContext,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?ContentIndexWorkManager $workManager = NULL,
    private readonly ?ContentIndexProviderRegistry $providerRegistry = NULL,
  ) {}

  /**
   * Implements hook_views_post_execute().
   *
   * Só age em views de datas de evento. As demais passam intactas.
   */
  #[Hook('views_post_execute')]
  public function viewsPostExecute(ViewExecutable $view): void {
    // A listagem administrativa mantém o histórico completo.
    if (!$this->listaDatas($view) || $this->adminContext->isAdminRoute()) {
      return;
    }

    $agora = $this->tempo->getCurrentTime();
    $ocorrencias = [];
    foreach ($view->result as $indice => $linha) {
      $entidade = $linha->_entity ?? NULL;
      if (!$entidade instanceof EventDate) {
        continue;
      }
      $fim = $this->proximas->fimDaData($entidade);
      if ($fim === NULL) {
        continue;
      }
      if ($fim->getTimestamp() <= $agora) {
        unset($view->result[$indice]);
        if ($view->total_rows !== NULL) {
          $view->total_rows--;
        }
        continue;
      }
      $ocorrencias[] = ['fim' => $fim];
    }

    // O conjunto muda quando a próxima data terminar, não a cada requisição.
    $validade = $this->proximas->validade($ocorrencias, $agora);
    if ($validade !== NULL) {
      $view->element['#cache']['max-age'] = $validade;
    }
  }

  /**
   * Invalida as listagens quando evento, data ou exceção de ocorrência mudam.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entidade salva ou excluída.
   */
  #[Hook('aculta_event_insert')]
  #[Hook('aculta_event_update')]
  #[Hook('aculta_event_delete')]
  #[Hook('aculta_event_date_insert')]
  #[Hook('aculta_event_date_update')]
  #[Hook('aculta_event_date_delete')]
  #[Hook('aculta_event_override_insert')]
  #[Hook('aculta_event_override_update')]
  #[Hook('aculta_event_override_delete')]
  #[Hook('aculta_event_location_insert')]
  #[Hook('aculta_event_location_update')]
  #[Hook('aculta_event_location_delete')]
  public function invalidarLista(EntityInterface $entity): void {
    Cache::invalidateTags([EventCacheTags::LISTA, 'aculta_event_location_list']);

    // Quando datas são inseridas, atualizadas ou excluídas, agenda reprojeção
    // do evento pai para atualizar relações event_location no Index (LOC-04).
    if ($entity instanceof EventDate && $this->workManager !== NULL) {
      $eventId = (int) ($entity->get('field_evento')->target_id ?? 0);
      if ($eventId > 0) {
        $event = $this->entityTypeManager->getStorage('aculta_event')->load($eventId);
        if ($event instanceof ContentEntityInterface) {
          $this->scheduleEventProjection($event);
        }
      }

      // Se a data mudou de evento, reprojeta também o evento anterior.
      $original = method_exists($entity, 'getOriginal') ? $entity->getOriginal() : (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => $entity->getOriginal(), fn() => $entity->original) ?? NULL);
      if ($original instanceof EventDate) {
        $oldEventId = (int) ($original->get('field_evento')->target_id ?? 0);
        if ($oldEventId > 0 && $oldEventId !== $eventId) {
          $oldEvent = $this->entityTypeManager->getStorage('aculta_event')->load($oldEventId);
          if ($oldEvent instanceof ContentEntityInterface) {
            $this->scheduleEventProjection($oldEvent);
          }
        }
      }
    }
    elseif ($entity instanceof EventLocation && $this->workManager !== NULL) {
      // Ao mudar ou excluir o Local, reprojeta os Eventos com datas nele.
      $dateIds = $this->entityTypeManager->getStorage('aculta_event_date')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('field_local', (int) $entity->id())
        ->execute();

      if (!empty($dateIds)) {
        $dates = $this->entityTypeManager->getStorage('aculta_event_date')->loadMultiple($dateIds);
        $eventIds = [];
        foreach ($dates as $date) {
          $eid = (int) ($date->get('field_evento')->target_id ?? 0);
          if ($eid > 0) {
            $eventIds[$eid] = $eid;
          }
        }
        if (!empty($eventIds)) {
          $events = $this->entityTypeManager->getStorage('aculta_event')->loadMultiple($eventIds);
          foreach ($events as $event) {
            if ($event instanceof ContentEntityInterface) {
              $this->scheduleEventProjection($event);
            }
          }
        }
      }
    }
  }

  /**
   * Agenda reprojeção de evento no Content Index work manager.
   */
  private function scheduleEventProjection(ContentEntityInterface $event): void {
    if ($this->workManager === NULL) {
      return;
    }
    $providerId = 'aculta_event';
    if ($this->providerRegistry !== NULL) {
      $resolved = $this->providerRegistry->findProviderIdForBundle($event->getEntityTypeId(), $event->bundle());
      if ($resolved !== NULL) {
        $providerId = $resolved;
      }
    }
    foreach ($event->getTranslationLanguages() as $langcode => $language) {
      if ($event->hasTranslation($langcode)) {
        $this->workManager->schedule($providerId, $event->getEntityTypeId(), (string) $event->id(), $langcode, 'project');
      }
    }
  }

  /**
   * A view lista datas de evento?
   */
  private function listaDatas(ViewExecutable $view): bool {
    return $view->storage->get('base_table') === 'aculta_event_date';
  }

}

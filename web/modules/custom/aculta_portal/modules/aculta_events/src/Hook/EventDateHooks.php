<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Hook;

use Drupal\aculta_events\Cache\EventCacheTags;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Occurrence\UpcomingDates;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;
use Drupal\views\ViewExecutable;

/**
 * Datas passadas fora das listagens, sem cron invalidando cache (auditoria, T5).
 *
 * Equivalente ao `viewsPostExecute` do OpenCulturas, escrito do zero: uma view cuja
 * tabela base é `aculta_event_date` perde as linhas cuja data já terminou, corrige
 * `total_rows` e declara max-age até o fim da próxima ocorrência. No instante em que
 * a data passa, o cache vence e a linha sai; nenhuma tarefa agendada é necessária.
 *
 * A tag de lista fecha o outro lado: criar, editar ou excluir evento, data ou exceção
 * de ocorrência invalida a listagem, para que o max-age até a próxima data não congele
 * conteúdo editado.
 *
 * Limite conhecido: a remoção acontece depois da consulta. A página que contiver a
 * linha removida mostra um item a menos, mas a contagem e a paginação continuam
 * coerentes porque `total_rows` é ajustado. A view do M2 deve filtrar por período na
 * consulta quando o período for fixo; este hook cobre só o que muda com o relógio.
 */
final class EventDateHooks {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly TimeInterface $tempo,
    private readonly UpcomingDates $proximas,
    private readonly AdminContext $adminContext,
  ) {}

  /**
   * Implements hook_views_post_execute().
   *
   * Só age em views de datas de evento. As demais passam intactas.
   */
  #[Hook('views_post_execute')]
  public function viewsPostExecute(ViewExecutable $view): void {
    // Listagem administrativa mostra o histórico inteiro: não filtra por relógio.
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
  public function invalidarLista(EntityInterface $entity): void {
    Cache::invalidateTags([EventCacheTags::LISTA]);

    // Quando datas são inseridas, atualizadas ou excluídas, agenda reprojeção
    // do evento pai para atualizar as relações event_location no Content Index (LOC-04).
    if ($entity instanceof EventDate && \Drupal::hasService('aculta_index.work_manager')) {
      $entityTypeManager = \Drupal::entityTypeManager();

      $eventId = (int) ($entity->get('field_evento')->target_id ?? 0);
      if ($eventId > 0) {
        $event = $entityTypeManager->getStorage('aculta_event')->load($eventId);
        if ($event instanceof ContentEntityInterface) {
          $this->scheduleEventProjection($event);
        }
      }

      // Se a data mudou de evento, reprojeta também o evento anterior.
      $original = method_exists($entity, 'getOriginal') ? $entity->getOriginal() : ($entity->original ?? NULL);
      if ($original instanceof EventDate) {
        $oldEventId = (int) ($original->get('field_evento')->target_id ?? 0);
        if ($oldEventId > 0 && $oldEventId !== $eventId) {
          $oldEvent = $entityTypeManager->getStorage('aculta_event')->load($oldEventId);
          if ($oldEvent instanceof ContentEntityInterface) {
            $this->scheduleEventProjection($oldEvent);
          }
        }
      }
    }
  }

  /**
   * Agenda reprojeção de evento no Content Index work manager.
   */
  private function scheduleEventProjection(ContentEntityInterface $event): void {
    if (!\Drupal::hasService('aculta_index.work_manager')) {
      return;
    }
    /** @var \Drupal\aculta_index\Queue\ContentIndexWorkManager $workManager */
    $workManager = \Drupal::service('aculta_index.work_manager');
    $providerId = 'aculta_event';
    if (\Drupal::hasService('aculta_index.provider_registry')) {
      /** @var \Drupal\aculta_index\Provider\ContentIndexProviderRegistry $registry */
      $registry = \Drupal::service('aculta_index.provider_registry');
      $resolved = $registry->findProviderIdForBundle($event->getEntityTypeId(), $event->bundle());
      if ($resolved !== NULL) {
        $providerId = $resolved;
      }
    }
    foreach ($event->getTranslationLanguages() as $langcode => $language) {
      if ($event->hasTranslation($langcode)) {
        $workManager->schedule($providerId, $event->getEntityTypeId(), (string) $event->id(), $langcode, 'project');
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

<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Occurrence\EventDateOccurrences;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Página pública de um evento (/eventos/{slug}): descrição, datas, locais e organizador.
 *
 * Rascunho e slug inexistente respondem 404, sem confirmar que o evento existe.
 * Cancelado aparece com aviso (MODELO-DE-DADOS.md).
 */
final class EventPageController extends ControllerBase {

  /**
   * Horizonte de datas listadas na página: um ano.
   */
  private const HORIZONTE_DIAS = 365;

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EventDateOccurrences $ocorrencias,
    private readonly TimeInterface $tempo,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_events.occurrences'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Página do evento.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function page(string $slug): array {
    $evento = $this->eventoPorSlug($slug);

    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))->setTimezone($fuso);
    $linhas = [];
    foreach ($this->datas($evento) as $data) {
      $local = $data->get('field_local')->entity?->label();
      foreach ($this->ocorrencias->expandir($data, $agora, $agora->modify('+' . self::HORIZONTE_DIAS . ' days'), $agora) as $ocorrencia) {
        $texto = Html::escape($ocorrencia['inicio']->format('d/m/Y H:i') . ' às ' . $ocorrencia['fim']->format('H:i'));
        if ($local) {
          $texto .= ' — ' . Html::escape((string) $local);
        }
        if ($ocorrencia['cancelada'] || $evento->get('field_status')->value === 'cancelado') {
          $texto .= ' <strong>(cancelado)</strong>';
        }
        $linhas[] = ['#markup' => $texto];
      }
    }

    $organizador = $evento->get('field_organizador')->entity;
    return [
      '#theme' => 'aculta_event_page',
      '#titulo' => (string) $evento->label(),
      '#subtitulo' => (string) $evento->get('field_subtitulo')->value,
      '#descricao' => (string) $evento->get('body')->value,
      '#tipo' => $evento->get('field_tipo')->entity?->label(),
      '#organizador' => $organizador?->getDisplayName(),
      '#url' => (string) $evento->get('field_url')->value,
      '#datas' => $linhas,
      '#cancelado' => $evento->get('field_status')->value === 'cancelado',
      '#cache' => ['tags' => $evento->getCacheTags(), 'max-age' => 900],
    ];
  }

  /**
   * Título da página, com o nome do evento.
   */
  public function titulo(string $slug): string {
    return (string) $this->eventoPorSlug($slug)->label();
  }

  /**
   * Evento publicado ou cancelado pelo slug; rascunho e inexistente são 404.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  private function eventoPorSlug(string $slug): Event {
    $eventos = $this->entities->getStorage('aculta_event')->loadByProperties(['field_slug' => $slug]);
    $evento = $eventos ? reset($eventos) : NULL;
    if (!$evento instanceof Event || !$evento->access('view')) {
      throw new NotFoundHttpException();
    }
    return $evento;
  }

  /**
   * Datas (EventDate) do evento.
   *
   * @return \Drupal\aculta_events\Entity\EventDate[]
   */
  private function datas(Event $evento): array {
    $storage = $this->entities->getStorage('aculta_event_date');
    $ids = $storage->getQuery()->condition('field_evento.target_id', $evento->id())->accessCheck(FALSE)->execute();
    return array_values(array_filter($storage->loadMultiple($ids), static fn ($d) => $d instanceof EventDate));
  }

}

<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Cache\EventCacheTags;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Occurrence\UpcomingDates;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Página pública de um evento com descrição, datas, locais e organizador.
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
   * Validade do cache quando o evento não tem data futura: 15 minutos.
   */
  private const VALIDADE_PADRAO = 900;

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly UpcomingDates $proximas,
    private readonly TimeInterface $tempo,
    private readonly ?DomainPurposeManager $purposes = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_events.upcoming'),
      $container->get('datetime.time'),
      $container->has('aculta_portal.domain_purpose') ? $container->get('aculta_portal.domain_purpose') : NULL,
    );
  }

  /**
   * Página do evento.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function page(string $slug): array {
    $evento = $this->eventoPorSlug($slug);

    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))
      ->setTimezone(new \DateTimeZone(UpcomingDates::FUSO));
    $ocorrencias = $this->proximas
      ->doEvento($evento, $agora, self::HORIZONTE_DIAS);

    $linhas = [];
    $podeEditarRecorrencia = $this->currentUser()->hasPermission('administer aculta events')
      && $this->purposes?->getCurrentPurpose() === 'main';
    // A tag de lista cobre data criada, editada ou excluída.
    // Evita cache longo com um conjunto de datas desatualizado.
    $tags = array_merge($evento->getCacheTags(), [EventCacheTags::LISTA]);
    foreach ($ocorrencias as $ocorrencia) {
      $local = $ocorrencia['data']->get('field_local')->entity;
      $texto = Html::escape($ocorrencia['inicio']->format('d/m/Y H:i') . ' às ' . $ocorrencia['fim']->format('H:i'));
      if ($local instanceof EventLocation) {
        if ($local->access('view', $this->currentUser())) {
          $localUrl = Url::fromRoute('aculta_events.location_canonical', [
            'aculta_event_location' => $local->id(),
          ])->toString();
          $texto .= ' — <a href="' . Html::escape($localUrl) . '">' . Html::escape((string) $local->label()) . '</a>';
        }
        else {
          $texto .= ' — ' . Html::escape((string) $local->label());
        }
        $tags = array_merge($tags, $local->getCacheTags());
      }
      if ($ocorrencia['cancelada'] || $evento->get('field_status')->value === 'cancelado') {
        $texto .= ' <strong>(cancelado)</strong>';
      }
      if ($local instanceof EventLocation && !$local->get('field_osm_id')->isEmpty()) {
        $texto .= ' — <a href="https://www.openstreetmap.org/copyright" rel="noopener">'
          . Html::escape((string) $this->t('© OpenStreetMap contributors')) . '</a>';
      }
      $data = $ocorrencia['data'];
      if ($podeEditarRecorrencia && !$data->get('field_regra')->isEmpty()) {
        $id = (string) $data->id();
        $urlRegra = Url::fromRoute('aculta_events.date_rule', ['aculta_event_date' => $id])->toString();
        $urlOcorrencia = Url::fromRoute('aculta_events.occurrence', [
          'aculta_event_date' => $id,
          'inicio' => (string) $ocorrencia['original']->getTimestamp(),
        ])->toString();
        $texto .= ' — <a href="' . Html::escape($urlRegra) . '">'
          . Html::escape((string) $this->t('Editar recorrência')) . '</a>';
        $texto .= ' · <a href="' . Html::escape($urlOcorrencia) . '">'
          . Html::escape((string) $this->t('Editar esta ocorrência')) . '</a>';
      }
      $linha = ['#markup' => $texto];
      $linhas[] = $linha;
    }

    // O conjunto de datas muda quando a próxima termina, não a cada requisição.
    $validade = $this->proximas->validade($ocorrencias, $this->tempo->getCurrentTime()) ?? self::VALIDADE_PADRAO;

    // Dados schema.org (Event), uma entrada por ocorrência listada (até 20).
    $eventosLd = [];
    $urlEvento = Url::fromRoute('aculta_events.page', ['slug' => (string) $evento->get('field_slug')->value], ['absolute' => TRUE])->toString();
    $descricao = mb_substr(trim(strip_tags((string) $evento->get('body')->value)), 0, 500);
    foreach (array_slice($ocorrencias, 0, 20) as $ocorrencia) {
      $local = $ocorrencia['data']->get('field_local')->entity;
      $entrada = [
        '@type' => 'Event',
        'name' => (string) $evento->label(),
        'url' => $urlEvento,
        'startDate' => $ocorrencia['inicio']->format('c'),
        'endDate' => $ocorrencia['fim']->format('c'),
        'eventStatus' => ($ocorrencia['cancelada'] || $evento->get('field_status')->value === 'cancelado')
          ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
      ];
      if ($descricao !== '') {
        $entrada['description'] = $descricao;
      }
      if ($local !== NULL) {
        $entrada['location'] = ['@type' => 'Place', 'name' => (string) $local->label()];
      }
      $eventosLd[] = $entrada;
    }
    $anexos = [];
    if ($eventosLd !== []) {
      // Escapa "</" para que o JSON não feche a tag de script por acidente.
      $jsonLd = json_encode(
        ['@context' => 'https://schema.org', '@graph' => $eventosLd],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
      );
      $json = str_replace('</', '<\\/', (string) $jsonLd);
      $anexos['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#attributes' => ['type' => 'application/ld+json'],
          '#value' => $json,
        ],
        'aculta_events_event_jsonld',
      ];
    }

    $prefix = '';
    if ($this->purposes?->getCurrentPurpose() === 'main' && $evento->access('update')) {
      // A administração vive no MAIN; a página pública no purpose eventos.
      $adminPath = '/painel-administrativo/eventos/' . $evento->id() . '/editar';
      $adminUrl = $this->purposes?->environmentPathUrl('main', $adminPath);
      $editUrl = $adminUrl?->toString()
        ?? Url::fromRoute('aculta_events.event_edit', ['aculta_event' => $evento->id()])->toString();
      $prefix = '<div class="event-admin-actions mb-4"><a href="' . Html::escape($editUrl) . '" class="button button--primary">' . $this->t('Editar evento e datas') . '</a></div>';
    }

    $organizador = $evento->get('field_organizador')->entity;
    return [
      '#theme' => 'aculta_event_page',
      '#prefix' => $prefix !== '' ? $prefix : NULL,
      '#titulo' => (string) $evento->label(),
      '#subtitulo' => (string) $evento->get('field_subtitulo')->value,
      '#descricao' => (string) $evento->get('body')->value,
      '#tipo' => $evento->get('field_tipo')->entity?->label(),
      '#organizador' => $organizador?->getDisplayName(),
      '#url' => (string) $evento->get('field_url')->value,
      '#datas' => $linhas,
      '#cancelado' => $evento->get('field_status')->value === 'cancelado',
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => array_values(array_unique($tags)),
        'max-age' => $validade,
      ],
      '#attached' => $anexos,
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

}

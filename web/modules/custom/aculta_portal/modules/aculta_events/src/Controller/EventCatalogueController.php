<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Cache\EventCacheTags;
use Drupal\aculta_events\Catalogue\EventCatalogue;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_events\Occurrence\UpcomingDates;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Catálogo público de eventos (/eventos), com filtros por tipo e período.
 *
 * Período e datas usam o formato brasileiro (dd/mm/aaaa). Data inválida é
 * ignorada e aparece como aviso, sem derrubar a página.
 */
final class EventCatalogueController extends ControllerBase {

  /**
   * Validade do cache quando não há evento futuro na listagem: 15 minutos.
   */
  private const VALIDADE_PADRAO = 900;

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EventCatalogue $catalogo,
    private readonly UpcomingDates $proximas,
    private readonly TimeInterface $tempo,
    private readonly Request $requisicao,
    private readonly ?DomainPurposeManager $purposes = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_events.catalogue'),
      $container->get('aculta_events.upcoming'),
      $container->get('datetime.time'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->has('aculta_portal.domain_purpose') ? $container->get('aculta_portal.domain_purpose') : NULL,
    );
  }

  /**
   * Página do catálogo.
   */
  public function page(): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $tipo = $this->requisicao->query->getInt('tipo') ?: NULL;
    $avisos = [];
    $de = $this->dataBr((string) $this->requisicao->query->get('de'), $fuso, $avisos, 'início');
    $ate = $this->dataBr((string) $this->requisicao->query->get('ate'), $fuso, $avisos, 'fim');

    $agoraSegundos = $this->tempo->getCurrentTime();
    $agora = (new \DateTimeImmutable('@' . $agoraSegundos))->setTimezone($fuso);
    $resultados = $this->catalogo->busca($tipo, $de, $ate, $agora);

    // A listagem muda quando a próxima data de cada evento termina (T5).
    $fins = array_map(static fn (array $item): array => ['fim' => $item['proxima']['fim']], $resultados);
    $validade = $this->proximas->validade($fins, $agoraSegundos) ?? self::VALIDADE_PADRAO;

    $itens = [];
    // Ações editoriais existem somente no MAIN; o catálogo em eventos é público.
    if ($this->purposes?->getCurrentPurpose() === 'main'
      && $this->entityTypeManager()->getAccessControlHandler('aculta_event')->createAccess(NULL, $this->currentUser())) {
      $itens[] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-catalogue-actions-bar'],
        'add_btn' => [
          '#type' => 'link',
          '#title' => $this->t('+ Cadastrar novo evento'),
          '#url' => Url::fromRoute('aculta_events.event_add'),
          '#attributes' => ['data-portal-ui' => 'event-location-button-primary-action'],
        ],
      ];
    }
    foreach ($resultados as $item) {
      $evento = $item['evento'];
      $cancelado = $evento->get('field_status')->value === 'cancelado';
      $card_tokens = $cancelado ? 'event-card event-card-cancelled' : 'event-card';
      $itens[] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => $card_tokens],
        'type' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['data-portal-ui' => 'event-card-type'],
          '#value' => Html::escape((string) ($evento->get('field_tipo')->entity?->label() ?? '')),
          '#access' => $evento->get('field_tipo')->entity !== NULL,
        ],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => ['data-portal-ui' => 'event-card-title'],
          'link' => [
            '#type' => 'link',
            '#title' => (string) $evento->label(),
            '#url' => Url::fromRoute('aculta_events.page', ['slug' => (string) $evento->get('field_slug')->value]),
          ],
        ],
        'subtitle' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['data-portal-ui' => 'event-card-subtitle'],
          '#value' => Html::escape((string) $evento->get('field_subtitulo')->value),
          '#access' => (string) $evento->get('field_subtitulo')->value !== '',
        ],
        'meta' => [
          '#theme' => 'item_list',
          '#attributes' => ['data-portal-ui' => 'event-card-meta'],
          '#items' => [$this->t('Próxima data: @date', ['@date' => $item['proxima']['inicio']->format('d/m/Y H:i')])],
        ],
        'cancelled' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['role' => 'status', 'data-portal-ui' => 'event-card-cancel-status'],
          '#value' => $this->t('Cancelado'),
          '#access' => $cancelado,
        ],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $itens,
      '#empty' => $this->t('Nenhum evento encontrado para este período.'),
      '#prefix' => $this->avisos($avisos),
      '#cache' => ['contexts' => ['url.query_args', 'user'], 'tags' => [EventCacheTags::LISTA], 'max-age' => $validade],
    ];
  }

  /**
   * Converte dd/mm/aaaa em data de Brasília; vazio vira NULL.
   *
   * Um valor inválido gera aviso.
   */
  private function dataBr(string $valor, \DateTimeZone $fuso, array &$avisos, string $rotulo): ?\DateTimeImmutable {
    if ($valor === '') {
      return NULL;
    }
    $data = \DateTimeImmutable::createFromFormat('!d/m/Y', $valor, $fuso);
    if ($data === FALSE || $data->format('d/m/Y') !== $valor) {
      $avisos[] = $this->t('Data de @rotulo inválida: @valor. Use dd/mm/aaaa.', [
        '@rotulo' => $rotulo,
        '@valor' => $valor,
      ]);
      return NULL;
    }
    return $data;
  }

  /**
   * Avisos de filtro como lista de mensagens.
   */
  private function avisos(array $avisos): string {
    if ($avisos === []) {
      return '';
    }
    $html = '';
    foreach ($avisos as $aviso) {
      $html .= '<p role="alert">' . Html::escape((string) $aviso) . '</p>';
    }
    return $html;
  }

}

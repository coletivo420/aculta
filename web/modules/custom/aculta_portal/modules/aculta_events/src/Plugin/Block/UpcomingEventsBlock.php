<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Plugin\Block;

use Drupal\aculta_events\Catalogue\EventCatalogue;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Read-only upcoming events block for any portal domain.
 *
 * Events are created and edited only in the eventos purpose. Other domains may
 * display this block, which links to the canonical eventos URLs and never
 * exposes editorial actions.
 */
#[Block(
  id: 'aculta_events_upcoming',
  admin_label: new TranslatableMarkup('Próximos eventos (somente leitura)'),
  category: new TranslatableMarkup('ACULTA Eventos'),
)]
final class UpcomingEventsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Número de eventos exibidos.
   */
  private const LIMITE = 5;

  /**
   * Vida do cache enquanto nenhuma data muda.
   */
  private const MAX_AGE = 900;

  /**
   * Construtor.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EventCatalogue $catalogue,
    private readonly DomainPurposeManager $purposes,
    private readonly TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_events.catalogue'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $agora = (new \DateTimeImmutable('@' . $this->time->getCurrentTime()))->setTimezone($fuso);
    $resultados = $this->catalogue->busca(NULL, NULL, NULL, $agora, self::LIMITE);

    $itens = [];
    foreach ($resultados as $resultado) {
      $evento = $resultado['evento'];
      $inicio = $resultado['proxima']['inicio'];
      $rotulo = $evento->label() . ' — ' . $inicio->format('d/m/Y H:i');
      if (!empty($resultado['proxima']['cancelada'])) {
        $rotulo .= ' (' . $this->t('cancelado') . ')';
      }
      $itens[] = [
        '#type' => 'link',
        '#title' => $rotulo,
        '#url' => $evento->toCanonicalUrl($this->purposes),
        '#attributes' => ['data-portal-ui' => 'events-upcoming-link'],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $itens,
      '#empty' => $this->t('Nenhum evento publicado no momento.'),
      '#attributes' => ['data-portal-ui' => 'events-upcoming-block'],
      '#cache' => [
        // A visibilidade de eventos de turma do LMS depende da conta.
        'contexts' => ['user', 'url.site'],
        'tags' => ['aculta_event_list', 'aculta_event_date_list'],
        'max-age' => self::MAX_AGE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user', 'url.site']);
  }

}

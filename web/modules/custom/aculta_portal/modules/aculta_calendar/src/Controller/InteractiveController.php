<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Visão interativa do calendário (DHTMLX Scheduler), carregada só nesta rota.
 *
 * O componente só apresenta. O motor de recorrência, o .ics e a regra de acesso
 * continuam no aculta_calendar; a agenda em HTML é a alternativa sem JavaScript.
 */
final class InteractiveController extends ControllerBase {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly RouteMatchInterface $rotaAtual,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('current_route_match'));
  }

  /**
   * Página da visão interativa.
   */
  public function page(): array {
    $agenda = $this->url('agenda');
    return [
      'aviso' => ['#markup' => Markup::create('<p class="aculta-calendar-interactive__aviso">Visão interativa do calendário. Sem JavaScript, use a agenda em lista.</p>')],
      'componente' => ['#markup' => Markup::create('<div id="aculta-calendar-interactive" class="aculta-calendar-interactive" role="region" aria-label="Calendário interativo de eventos"></div>')],
      'alternativa' => ['#markup' => Markup::create($agenda === NULL ? '' : '<p class="aculta-calendar-interactive__alternativa"><a href="' . Html::escape($agenda) . '">Ver a agenda em lista</a></p>')],
      '#attached' => ['library' => ['aculta_calendar/dhtmlx']],
      '#cache' => [
        'tags' => ['aculta_calendar_item_list'],
        'contexts' => ['route'],
        'max-age' => 900,
      ],
    ];
  }

  /**
   * URL de uma rota do publisher, informada em `_aculta_calendar_rotas`.
   */
  private function url(string $chave): ?string {
    $rotas = $this->rotaAtual->getRouteObject()?->getOption('_aculta_calendar_rotas') ?? [];
    $nome = is_array($rotas) && is_string($rotas[$chave] ?? NULL) ? $rotas[$chave] : NULL;
    return $nome === NULL ? NULL : Url::fromRoute($nome)->toString();
  }

}

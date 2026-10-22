<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_studio\StudioListingBuilder;
use Drupal\aculta_studio\StudioPresenter;
use Drupal\aculta_studio\StudioTabInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Página do Studio em quatro URLs: /studio, /studio/{área}, /studio/{área}/{seção} e
 * /studio/{área}/{seção}/{aba}. Cada nível é um link real (funciona sem JavaScript) e o AJAX
 * (js/studio-navigation.js) apenas troca a raiz, mantendo a URL de cada nível.
 *
 * Um segmento que não existe, ou que a conta não pode usar, responde 404: a página não revela o que
 * está fora do alcance da conta. O módulo marca as partes com tokens data-portal-ui e atributos
 * data-aculta-studio de comportamento; o ACULTA420 decide a apresentação.
 */
final class StudioController {

  use StringTranslationTrait;

  public function __construct(
    private readonly StudioPresenter $presenter,
    private readonly AccountInterface $account,
    private readonly StudioListingBuilder $listings,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Renderiza o Studio no nível pedido pela URL.
   */
  public function page(string $aba = '', string $secao = '', string $sub = ''): array {
    $areas = $this->presenter->visibleTabs($this->account);
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'aculta-studio-root',
        'data-aculta-studio' => 'root',
        'data-portal-ui' => 'studio-shell',
      ],
      '#attached' => ['library' => ['aculta_studio/navigation']],
      '#cache' => ['contexts' => ['user.permissions', 'url.path']],
    ];
    // Região de avisos: o JavaScript escreve aqui o estado de carregamento e os erros de navegação.
    $build['live'] = [
      '#type' => 'container',
      '#attributes' => ['role' => 'status', 'aria-live' => 'polite', 'data-aculta-studio' => 'live', 'data-portal-ui' => 'studio-live'],
    ];
    if ($areas === []) {
      $build['empty'] = $this->element('p', $this->t('Nenhuma área do Estúdio está disponível para a sua conta.'), 'studio-empty', 'empty');
      return $build;
    }

    // Nível 1: a área pedida, ou a primeira quando a URL é /studio.
    if ($aba === '') {
      $aba = (string) array_key_first($areas);
    }
    $area = $areas[$aba] ?? throw new NotFoundHttpException();
    $build['areas'] = $this->linkList(
      array_map(static fn (StudioTabInterface $tab): array => ['id' => $tab->getPluginId(), 'label' => $tab->getPluginDefinition()['label'], 'url' => Url::fromRoute('aculta_studio.area', ['aba' => $tab->getPluginId()])], $areas),
      'tab',
      $area->getPluginId(),
      'studio-tabs',
      (string) $this->t('Áreas do Estúdio'),
    );

    // Nível 2: a seção pedida, ou a primeira da área.
    $sections = $this->presenter->sectionsFor($area, $this->account);
    if ($secao === '') {
      $secao = (string) array_key_first($sections);
    }
    $section = $sections[$secao] ?? throw new NotFoundHttpException();
    $build['layout'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-layout', 'data-aculta-studio' => 'layout'],
      'sidebar' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-sidebar', 'data-aculta-studio' => 'sidebar'],
        'sections' => $this->linkList(
          array_map(static fn (string $id, array $s): array => ['id' => $id, 'label' => $s['label'], 'url' => Url::fromRoute('aculta_studio.section', ['aba' => $area->getPluginId(), 'secao' => $id])], array_keys($sections), $sections),
          'section',
          $secao,
          'studio-sections',
          (string) $this->t('Submenu da área'),
        ),
      ],
      'main' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-main', 'data-aculta-studio' => 'main'],
        'heading' => $this->element('h2', $section['label'], 'studio-heading', 'heading'),
      ],
    ];

    // Nível 3: a aba pedida, ou a primeira da seção. As abas filtram ou mostram a visão da seção.
    if ($sub === '') {
      $sub = (string) array_key_first($section['tabs']);
    }
    $tab = $section['tabs'][$sub] ?? throw new NotFoundHttpException();
    $build['layout']['main']['subtabs'] = $this->linkList(
      array_map(static fn (string $id, array $t): array => ['id' => $id, 'label' => $t['label'], 'url' => Url::fromRoute('aculta_studio.tab', ['aba' => $area->getPluginId(), 'secao' => $secao, 'sub' => $id])], array_keys($section['tabs']), $section['tabs']),
      'subtab',
      $sub,
      'studio-subtabs',
      (string) $this->t('Abas da seção'),
    );

    $actions = [];
    foreach ($tab['actions'] as $action) {
      $actions[] = PortalLinkElement::build($action['title'], $action['url'], ['data-portal-ui' => 'studio-action', 'data-aculta-studio' => 'action']);
    }
    // Listagem da aba (quando a aba declara uma): as linhas passam pelo acesso de cada nó.
    if (!empty($tab['listing'])) {
      $build['layout']['main']['listing'] = $this->listings->build($tab['listing'], $this->account);
    }
    $build['layout']['main']['panel'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-panel', 'data-aculta-studio' => 'panel', 'data-aculta-studio-tab' => $sub],
      'actions' => [
        '#theme' => 'item_list',
        '#items' => $actions,
        '#attributes' => ['data-portal-ui' => 'studio-actions', 'data-aculta-studio' => 'actions'],
      ],
    ];
    return $build;
  }

  /**
   * Lista de links de um nível. O item do nível atual recebe aria-current="page".
   *
   * @param array<int, array{id: string, label: mixed, url: \Drupal\Core\Url}> $items
   */
  private function linkList(array $items, string $behaviour, string $activeId, string $token, string $label): array {
    $links = [];
    foreach ($items as $item) {
      $attributes = ['data-portal-ui' => $behaviour === 'tab' ? 'studio-tab' : 'studio-link', 'data-aculta-studio' => $behaviour];
      if ($item['id'] === $activeId) {
        $attributes['aria-current'] = 'page';
      }
      $links[] = PortalLinkElement::build($item['label'], $item['url'], $attributes);
    }
    $list = [
      '#theme' => 'item_list',
      '#items' => $links,
      '#attributes' => ['data-portal-ui' => $token, 'data-aculta-studio' => $behaviour . 's', 'aria-label' => $label],
    ];
    // Areas and subtabs are route navigation, not Bootstrap tab panels.
    // Keep real links, the Studio AJAX hooks, access and cache on the list.
    if ($behaviour === 'tab' || $behaviour === 'subtab') {
      return [
        '#type' => 'component',
        '#component' => 'aculta420:context-tabs',
        '#props' => ['label' => $label],
        '#slots' => ['items' => $list],
      ];
    }
    return $list;
  }

  /**
   * Elemento de texto neutro com token semântico e atributo de comportamento.
   */
  private function element(string $tag, mixed $value, string $token, string $behaviour): array {
    return [
      '#theme' => 'aculta_portal_element',
      '#tag' => $tag,
      '#value' => $value,
      '#element_attributes' => new Attribute(['data-portal-ui' => $token, 'data-aculta-studio' => $behaviour]),
    ];
  }

}

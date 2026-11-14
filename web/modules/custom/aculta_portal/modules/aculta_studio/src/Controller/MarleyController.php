<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_studio\MarleyListingBuilder;
use Drupal\aculta_studio\MarleyPresenter;
use Drupal\aculta_studio\MarleyTabInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders Marley editorial workspace routes.
 *
 * The workspace uses four URL levels. Each level is a real link and the AJAX
 * enhancement only replaces the root while preserving the URL.
 *
 * A segment outside the account's reach returns 404. The module marks content
 * with neutral behavior tokens; ACULTA420 owns its presentation.
 */
final class MarleyController {

  use StringTranslationTrait;

  public function __construct(
    private readonly MarleyPresenter $presenter,
    private readonly AccountInterface $account,
    private readonly MarleyListingBuilder $listings,
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
      '#cache' => [
        'contexts' => ['user.permissions', 'user.roles', 'url.path'],
        'tags' => ['config:aculta_magazine.settings'],
      ],
    ];
    // JavaScript reports navigation status and errors in this live region.
    $build['live'] = [
      '#type' => 'container',
      '#attributes' => [
        'role' => 'status',
        'aria-live' => 'polite',
        'data-aculta-studio' => 'live',
        'data-portal-ui' => 'studio-live',
      ],
    ];
    if ($areas === []) {
      $build['empty'] = $this->element('p', $this->t('Nenhuma área do Estúdio está disponível para a sua conta.'), 'studio-empty', 'empty');
      return $build;
    }

    // A entrada editorial oferece criação rápida antes da navegação por áreas.
    // Somente ações liberadas pelo proprietário entram nos atalhos.
    if ($aba === '') {
      $build['welcome'] = $this->welcome($areas);
    }

    // Nível 1: a área pedida, ou a primeira quando a URL é /marley.
    if ($aba === '') {
      $aba = (string) array_key_first($areas);
    }
    $area = $areas[$aba] ?? throw new NotFoundHttpException();
    $area_links = array_map(
      static fn (MarleyTabInterface $tab): array => [
        'id' => $tab->getPluginId(),
        'label' => $tab->getPluginDefinition()['label'],
        'url' => Url::fromRoute('aculta_studio.area', ['aba' => $tab->getPluginId()]),
      ],
      $areas,
    );
    $build['areas'] = $this->linkList(
      $area_links,
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
    // Só entram no submenu seções explicitamente navegáveis. Seções que
    // concentram as próprias ações podem suprimir o menu inteiro.
    $sidebar_links = [];
    foreach ($sections as $id => $candidate) {
      if (($candidate['sidebar'] ?? TRUE) === FALSE) {
        continue;
      }
      $sidebar_links[] = [
        'id' => $id,
        'label' => $candidate['label'],
        'url' => Url::fromRoute(
          'aculta_studio.section',
          ['aba' => $area->getPluginId(), 'secao' => $id],
        ),
      ];
    }
    $layout = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-layout', 'data-aculta-studio' => 'layout'],
    ];
    if ($sidebar_links !== [] && empty($section['suppress_sidebar'])) {
      $layout['sidebar'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-sidebar', 'data-aculta-studio' => 'sidebar'],
        'sections' => $this->linkList(
          $sidebar_links,
          'section',
          $secao,
          'studio-sections',
          (string) $this->t('Submenu da área'),
        ),
      ];
    }
    $layout['main'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-main', 'data-aculta-studio' => 'main'],
      'section_header' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-section-header', 'data-aculta-studio' => 'section-header'],
        'heading' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'h2',
          '#value' => $section['label'],
          '#element_attributes' => new Attribute([
            'data-portal-ui' => 'studio-heading',
            'data-aculta-studio' => 'heading',
            // O tema move o foco para este título após a troca HTMX.
            'data-aculta-focus' => '',
            'tabindex' => '-1',
          ]),
        ],
      ],
    ];
    // Ações da seção ficam junto ao título, antes das abas de status.
    $section_actions = [];
    foreach ($section['actions'] ?? [] as $action) {
      $section_actions[] = PortalLinkElement::build(
        $action['title'],
        $action['url'],
        ['data-portal-ui' => 'studio-action', 'data-aculta-studio' => 'section-action'] + ($action['attributes'] ?? []),
      );
    }
    if ($section_actions !== []) {
      $layout['main']['section_header']['actions'] = [
        '#theme' => 'item_list',
        '#items' => $section_actions,
        '#attributes' => ['data-portal-ui' => 'studio-actions', 'data-aculta-studio' => 'section-actions'],
      ];
    }
    $build['layout'] = $layout;
    // Popup de criação de conteúdo (ação Nova publicação): diálogo e HTMX.
    $build['#attached']['library'][] = 'aculta_studio/studio-create-popup';
    $build['studio_create_popup'] = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:popup',
      '#props' => [
        'id' => 'aculta-studio-create-dialog',
        'title' => (string) t('Nova publicação'),
        'close_label' => (string) t('Fechar'),
      ],
      '#slots' => [
        'content' => ['#markup' => '<div id="aculta-studio-create-body" class="aculta-reply-dialog__body"></div>'],
      ],
    ];

    // Nível 3: the requested subtab, or the first subtab in its section.
    if ($sub === '') {
      $sub = (string) array_key_first($section['tabs']);
    }
    $tab = $section['tabs'][$sub] ?? throw new NotFoundHttpException();
    $subtab_links = array_map(
      static fn (string $id, array $tab): array => [
        'id' => $id,
        'label' => $tab['label'],
        // Aba com ação de popup (Nova publicação): o link abre o mesmo popup da ação.
        'url' => ($tab['actions'][0]['attributes'] ?? NULL) !== NULL
          ? $tab['actions'][0]['url']
          : Url::fromRoute(
            'aculta_studio.tab',
            ['aba' => $area->getPluginId(), 'secao' => $secao, 'sub' => $id],
          ),
        'attributes' => $tab['actions'][0]['attributes'] ?? [],
      ],
      array_keys($section['tabs']),
      $section['tabs'],
    );
    $build['layout']['main']['subtabs'] = $this->linkList(
      $subtab_links,
      'subtab',
      $sub,
      'studio-subtabs',
      (string) $this->t('Abas da seção'),
    );

    $actions = [];
    foreach ($tab['actions'] as $action) {
      $actions[] = PortalLinkElement::build(
        $action['title'],
        $action['url'],
        ['data-portal-ui' => 'studio-action', 'data-aculta-studio' => 'action'] + ($action['attributes'] ?? []),
      );
    }
    // Each listing row respects the node's access result.
    if (!empty($tab['listing'])) {
      $build['layout']['main']['listing'] = $this->listings->build($tab['listing'], $this->account);
    }
    $build['layout']['main']['panel'] = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:surface-panel',
      '#props' => ['tone' => 'raised', 'radius' => 'md', 'spacing' => 'compact', 'elevation' => 'none'],
      '#attributes' => ['data-aculta-studio' => 'panel', 'data-aculta-studio-tab' => $sub],
      '#slots' => ['content' => ['actions' => [
        '#theme' => 'item_list',
        '#items' => $actions,
        '#attributes' => ['data-portal-ui' => 'studio-actions', 'data-aculta-studio' => 'actions'],
      ]]],
    ];
    return $build;
  }

  /**
   * Publication-first landing: native SDC composition and authorized links.
   *
   * @param array<string, \Drupal\aculta_studio\MarleyTabInterface> $areas
   *   Areas already filtered by the presenter.
   */
  private function welcome(array $areas): array {
    $items = [];
    foreach ($areas as $area) {
      foreach ($this->presenter->sectionsFor($area, $this->account) as $sectionId => $section) {
        if ($sectionId !== 'criar') {
          continue;
        }
        foreach ($section['tabs'] as $tab) {
          foreach ($tab['actions'] as $action) {
            $items[] = [
              '#theme' => 'aculta_portal_component',
              '#name' => 'card',
              '#slots' => [
                'header' => ['#plain_text' => (string) $action['title']],
                'content' => ['#plain_text' => (string) $area->getPluginDefinition()['label']],
                'footer' => PortalLinkElement::build($action['title'], $action['url'], [
                  'data-portal-ui' => 'studio-action',
                  'data-aculta-studio' => 'action',
                ] + ($action['attributes'] ?? [])),
              ],
            ];
          }
        }
      }
    }
    return [
      '#type' => 'container',
      '#attributes' => ['data-aculta-studio' => 'welcome'],
      'header' => [
        '#theme' => 'aculta_portal_component',
        '#name' => 'context-header',
        '#props' => [
          'eyebrow' => (string) $this->t('ACULTA Studio Marley'),
          'title' => (string) $this->t('O que vamos criar hoje?'),
          'subtitle' => (string) $this->t('Escolha como começar ou continue seu trabalho nas áreas abaixo.'),
        ],
      ],
      'create' => $items === [] ? [] : [
        '#theme' => 'aculta_portal_component',
        '#name' => 'content-grid',
        '#props' => ['columns' => 2],
        '#slots' => ['items' => $items],
      ],
    ];
  }

  /**
   * Builds authorized links and announces the current item accessibly.
   *
   * @param array<int, array{id: string, label: mixed, url: \Drupal\Core\Url}> $items
   *   Authorized navigation links to render.
   * @param string $behaviour
   *   The data attribute value identifying the link level.
   * @param string $activeId
   *   The active item identifier.
   * @param string $token
   *   The presentation token for this navigation list.
   * @param string $label
   *   The accessible name for the list.
   *
   * @return array
   *   A list or context-tabs render array.
   */
  private function linkList(array $items, string $behaviour, string $activeId, string $token, string $label): array {
    $links = [];
    foreach ($items as $item) {
      $attributes = [
        'data-portal-ui' => $behaviour === 'tab' ? 'studio-tab' : 'studio-link',
        'data-aculta-studio' => $behaviour,
      ];
      $attributes += $item['attributes'] ?? [];
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
        '#theme' => 'aculta_portal_component',
        '#name' => 'context-tabs',
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

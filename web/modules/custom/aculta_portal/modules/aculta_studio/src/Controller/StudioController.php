<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_studio\StudioPresenter;
use Drupal\aculta_studio\StudioTabInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Página do Studio em três níveis, sem o shell da conta:
 *   1. áreas em abas superiores (uma por dono: `aba`);
 *   2. submenu à esquerda com as seções da área (`secao`);
 *   3. abas da seção (`sub`) e, abaixo, as ações da aba escolhida.
 *
 * A troca em qualquer nível é AJAX pelo js/studio-navigation.js, que substitui a raiz #aculta-studio-root.
 * O módulo marca as partes com tokens data-portal-ui e atributos data-aculta-studio de comportamento.
 */
final class StudioController {

  use StringTranslationTrait;

  public function __construct(
    private readonly StudioPresenter $presenter,
    private readonly AccountInterface $account,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Renderiza o Studio para a conta atual.
   */
  public function page(Request $request): array {
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
        'contexts' => ['user.permissions', 'url.query_args:aba', 'url.query_args:secao', 'url.query_args:sub'],
      ],
    ];
    if ($areas === []) {
      $build['empty'] = $this->element('p', $this->t('Nenhuma área do Estúdio está disponível para a sua conta.'), 'studio-empty', 'empty');
      return $build;
    }

    // Nível 1: áreas em abas superiores.
    $areaId = $request->query->getString('aba');
    $area = $areas[$areaId] ?? reset($areas);
    $build['areas'] = $this->linkList(
      array_map(static fn (StudioTabInterface $tab): array => ['id' => $tab->getPluginId(), 'label' => $tab->getPluginDefinition()['label']], $areas),
      'tab',
      $area->getPluginId(),
      fn (string $id): array => ['aba' => $id],
      'studio-tabs',
      (string) $this->t('Áreas do Estúdio'),
    );

    // Nível 2: submenu à esquerda com as seções da área.
    $sections = $this->presenter->sectionsFor($area, $this->account);
    $sectionId = $request->query->getString('secao');
    $sectionId = isset($sections[$sectionId]) ? $sectionId : (string) array_key_first($sections);
    $section = $sections[$sectionId];
    $build['layout'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-layout', 'data-aculta-studio' => 'layout'],
      'sidebar' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-sidebar', 'data-aculta-studio' => 'sidebar'],
        'sections' => $this->linkList(
          array_map(static fn (string $id, array $s): array => ['id' => $id, 'label' => $s['label']], array_keys($sections), $sections),
          'section',
          $sectionId,
          fn (string $id): array => ['aba' => $area->getPluginId(), 'secao' => $id],
          'studio-sections',
          (string) $this->t('Submenu da área'),
        ),
      ],
      'main' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-main', 'data-aculta-studio' => 'main'],
        'heading' => $this->element('h2', $section['label'], 'studio-heading', 'heading'),
        // Nível 3: abas da seção escolhida.
        'subtabs' => $this->linkList(
          array_map(static fn (string $id, array $t): array => ['id' => $id, 'label' => $t['label']], array_keys($section['tabs']), $section['tabs']),
          'subtab',
          (string) array_key_first($section['tabs']),
          fn (string $id): array => ['aba' => $area->getPluginId(), 'secao' => $sectionId, 'sub' => $id],
          'studio-subtabs',
          (string) $this->t('Abas da seção'),
        ),
      ],
    ];

    // As ações da aba escolhida (ou da primeira, quando o argumento sub não existe).
    $subId = $request->query->getString('sub');
    $subId = isset($section['tabs'][$subId]) ? $subId : (string) array_key_first($section['tabs']);
    $actions = [];
    foreach ($section['tabs'][$subId]['actions'] as $action) {
      $actions[] = PortalLinkElement::build($action['title'], $action['url'], ['data-portal-ui' => 'studio-action', 'data-aculta-studio' => 'action']);
    }
    $build['layout']['main']['panel'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-panel', 'data-aculta-studio' => 'panel', 'data-aculta-studio-tab' => $subId],
      'actions' => [
        '#theme' => 'item_list',
        '#items' => $actions,
        '#attributes' => ['data-portal-ui' => 'studio-actions', 'data-aculta-studio' => 'actions'],
      ],
    ];
    return $build;
  }

  /**
   * Lista de links de um nível. O item ativo recebe aria-current="page".
   *
   * @param array<int, array{id: string, label: mixed}> $items
   */
  private function linkList(array $items, string $behaviour, string $activeId, callable $query, string $token, string $label): array {
    $links = [];
    foreach ($items as $item) {
      $attributes = ['data-portal-ui' => $behaviour === 'tab' ? 'studio-tab' : 'studio-link', 'data-aculta-studio' => $behaviour];
      if ($item['id'] === $activeId) {
        $attributes['aria-current'] = 'page';
      }
      $links[] = PortalLinkElement::build($item['label'], Url::fromRoute('aculta_studio.home', [], ['query' => $query($item['id'])]), $attributes);
    }
    return [
      '#theme' => 'item_list',
      '#items' => $links,
      '#attributes' => ['data-portal-ui' => $token, 'data-aculta-studio' => $behaviour . 's', 'aria-label' => $label],
    ];
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

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
 * Page of the Studio: one tab per owner, the active tab from the `aba` argument.
 *
 * The module only marks the parts with semantic tokens (data-portal-ui). The
 * ACULTA420 theme decides the presentation, as in docs/portal/FRONTEIRA-MODULO-TEMA.md.
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
   * Renders the Studio for the current account.
   */
  public function page(Request $request): array {
    $tabs = $this->presenter->visibleTabs($this->account);
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-shell'],
      '#cache' => [
        'contexts' => ['user.permissions', 'url.query_args:aba'],
      ],
    ];
    if ($tabs === []) {
      $build['empty'] = $this->element('p', $this->t('Nenhuma área do Estúdio está disponível para a sua conta.'), 'studio-empty');
      return $build;
    }

    $active = $request->query->getString('aba');
    $selected = $tabs[$active] ?? reset($tabs);
    $items = [];
    foreach ($tabs as $id => $tab) {
      $attributes = ['data-portal-ui' => 'studio-tab'];
      if ($id === $selected->getPluginId()) {
        $attributes['aria-current'] = 'page';
      }
      $items[] = PortalLinkElement::build(
        $tab->getPluginDefinition()['label'],
        Url::fromRoute('aculta_studio.home', [], ['query' => ['aba' => $id]]),
        $attributes,
      );
    }
    $build['tabs'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['data-portal-ui' => 'studio-tabs'],
    ];

    $actions = [];
    foreach ($selected->actions($this->account) as $action) {
      if (!$action['access']) {
        continue;
      }
      $actions[] = PortalLinkElement::build($action['title'], $action['url'], ['data-portal-ui' => 'studio-action']);
    }
    $build['panel'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-panel', 'data-aculta-studio-tab' => $selected->getPluginId()],
      'heading' => $this->element('h2', $selected->getPluginDefinition()['label'], 'studio-heading'),
      'actions' => [
        '#theme' => 'item_list',
        '#items' => $actions,
        '#attributes' => ['data-portal-ui' => 'studio-actions'],
      ],
    ];
    return $build;
  }

  /**
   * A neutral text element with a semantic token.
   */
  private function element(string $tag, mixed $value, string $token): array {
    return [
      '#theme' => 'aculta_portal_element',
      '#tag' => $tag,
      '#value' => $value,
      '#element_attributes' => new Attribute(['data-portal-ui' => $token]),
    ];
  }

}

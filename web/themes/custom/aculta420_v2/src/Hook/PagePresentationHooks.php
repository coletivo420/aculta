<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\aculta420_v2\Support\ComponentLibraryRoutes;
use Drupal\aculta420_v2\Presentation\WikiBrandMediaProps;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Maps the Portal's neutral Domain Presentation contract to theme variables.
 */
final class PagePresentationHooks {

  /**
   * Routes that render the statistics dashboard UI.
   */
  private const STATISTICS_ROUTES = [
    'aculta_statistics.dashboard',
    'aculta_statistics.area',
  ];

  /**
   * Routes that render organization profiles, widgets, or the organization table.
   */
  private const EVENT_ORGANIZATION_ROUTES = [
    'aculta_events.organizer_event_add',
    'aculta_events.organizer_event_edit',
    'aculta_events.event_add',
    'aculta_events.event_edit',
    'aculta_events.organization_page',
    'aculta_events.admin_organizations',
    'aculta_events.organization_modal',
    'aculta_events.organizer_organization_modal',
  ];

  public function __construct(
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'extension.list.theme')]
    private readonly ExtensionList $themes,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'current_route_match')]
    private readonly ?CurrentRouteMatch $currentRouteMatch = NULL,
    #[Autowire(service: 'path.matcher')]
    private readonly ?PathMatcherInterface $pathMatcher = NULL,
    #[Autowire(service: 'plugin.manager.sdc')]
    private readonly ?ComponentPluginManager $componentManager = NULL,
  ) {}

  /**
   * Exposes the legacy homepage showcase prepared by ACULTA Portal.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $routeName = (string) $this->currentRouteMatch?->getRouteName();
    $variables['aculta_studio_account_dashboard'] = $routeName === 'aculta_studio.account';
    $variables['aculta_layout_variant'] = $this->getPageLayoutVariant();
    // ADR-054: shell paralelo, opt-in pela configuração do tema.
    $variables['aculta_shell_variant'] = $this->shellVariant();
    if ($variables['aculta_shell_variant'] === 'v2') {
      $variables['#attached']['library'][] = 'aculta420_v2/shell-v2';
    }
    // ADR-054 P7: CSS de estatísticas carregado só nas rotas do painel.
    if (in_array($this->currentRouteMatch?->getRouteName(), self::STATISTICS_ROUTES, TRUE)) {
      $variables['#attached']['library'][] = 'aculta420_v2/statistics';
    }
    if ($this->currentRouteMatch?->getRouteName() === 'user.login') {
      $variables['#attached']['library'][] = 'aculta420_v2/login-page';
    }
    if ($this->currentRouteMatch?->getRouteName() === 'aculta_portal.search') {
      $variables['#attached']['library'][] = 'aculta420_v2/search-page';
    }
    if ($this->currentRouteMatch?->getRouteName() === 'aculta_portal.support_form'
      && isset($variables['page']['content'])
      && is_array($variables['page']['content'])
      && $this->componentAvailable('support-form')) {
      $this->wrapPortalUiRenderArray($variables['page']['content'], 'support-form', 'support-form');
    }
    if ($this->currentRouteMatch?->getRouteName() === 'aculta_linkweed.social_links_settings') {
      $variables['#attached']['library'][] = 'aculta420_v2/linkweed-admin';
    }
    if (str_starts_with((string) $this->currentRouteMatch?->getRouteName(), 'aculta_portal.secrets_')) {
      $variables['#attached']['library'][] = 'aculta420_v2/secrets-admin';
    }
    // ADR-054 P7: CSS do Studio carregado só nas rotas do Studio/Conta.
    if ($routeName === 'aculta_lms.course_structure'
      && isset($variables['page']['content'])
      && is_array($variables['page']['content'])
      && ($variables['page']['content']['#component'] ?? NULL) === 'aculta420_v2:lms-course-curriculum'
      && isset($variables['page']['content']['#slots'])
      && is_array($variables['page']['content']['#slots'])
      && isset($variables['page']['content']['aculta_lms_course_builder_resources'])
      && is_array($variables['page']['content']['aculta_lms_course_builder_resources'])) {
      $variables['page']['content']['#slots']['resources'] = $variables['page']['content']['aculta_lms_course_builder_resources'];
      unset($variables['page']['content']['aculta_lms_course_builder_resources']);
    }
    if (isset($variables['page']['content']) && is_array($variables['page']['content'])) {
      if (in_array($routeName, ['aculta_portal.dashboard', 'aculta_studio.profile'], TRUE)
        && $this->componentAvailable('account-profile-card')) {
        foreach (['account-bio-card', 'account-social-card'] as $accountProfileToken) {
          while ($this->wrapPortalUiRenderArray(
            $variables['page']['content'],
            $accountProfileToken,
            'account-profile-card',
          )) {
            // The legacy dashboard and the Studio profile both render these cards.
          }
        }
      }
      if ($routeName === 'aculta_lms.course_create'
        && $this->componentAvailable('studio-course-authoring-page')) {
        $this->wrapPortalUiRenderArray(
          $variables['page']['content'],
          'studio-course-authoring',
          'studio-course-authoring-page',
        );
      }
      if (str_starts_with($routeName, 'aculta_studio.')
        && $this->componentAvailable('studio-revista-form')) {
        $this->wrapStudioRevistaForm($variables['page']['content']);
      }
      if ((str_starts_with($routeName, 'aculta_studio.') || $routeName === 'aculta_lms.course_create')
        && $this->componentAvailable('studio-form')) {
        $this->wrapStudioForms($variables['page']['content']);
      }
      if (str_starts_with($routeName, 'aculta_studio.')
        && $this->componentAvailable('studio-section-header')) {
        while ($this->wrapPortalUiRenderArray(
          $variables['page']['content'],
          'studio-section-header',
          'studio-section-header',
        )) {
          // Cada título de seção ganha seu asset sem alterar a saída do módulo.
        }
      }
      if (str_starts_with($routeName, 'aculta_studio.')
        && $this->componentAvailable('studio-listing-table')) {
        while ($this->wrapPortalUiRenderArray(
          $variables['page']['content'],
          'studio-listing-wrap',
          'studio-listing-table',
        )) {
          // Each table is a separate responsive region in the source output.
        }
      }
      if (str_starts_with($routeName, 'aculta_studio.account')
        && $this->componentAvailable('studio-account-section-layout')) {
        $this->wrapPortalUiRenderArray(
          $variables['page']['content'],
          'studio-account-section',
          'studio-account-section-layout',
        );
      }
      if (in_array($routeName, [
        'aculta_commerce.green_stash',
        'aculta_commerce.green_stash_area',
        'aculta_commerce.green_stash_product_detail',
        'aculta_commerce.green_stash_product_create',
      ], TRUE) && $this->componentAvailable('green-stash-page')) {
        $greenStashPage = $variables['page']['content'];
        $variables['page']['content'] = [
          '#type' => 'component',
          '#component' => 'aculta420_v2:green-stash-page',
          '#props' => [],
          '#slots' => ['content' => $greenStashPage],
        ];
      }
      if ($routeName === 'aculta_events.map' && $this->componentAvailable('map-canvas')) {
        $mapPage = $variables['page']['content'];
        $variables['page']['content'] = [
          '#type' => 'component',
          '#component' => 'aculta420_v2:map-canvas',
          '#props' => [],
          '#slots' => ['content' => $mapPage],
        ];
      }
      else {
        $this->wrapPortalUiRenderArray($variables['page']['content'], 'map-wrapper', 'map-canvas');
        $this->wrapPortalUiRenderArray($variables['page']['content'], 'map-widget', 'map-canvas');
      }
      if ($routeName === 'aculta_lms.answer_submission' && $this->componentAvailable('lms-learning-room')) {
        $this->wrapPortalUiRenderArray(
          $variables['page']['content'],
          'aculta-learning-room',
          'lms-learning-room',
        );
      }
    }
    if (str_starts_with($routeName, 'aculta_studio.')) {
      if (isset($variables['page']['content'])
        && is_array($variables['page']['content'])
        && $this->componentAvailable('studio-shell')) {
        $studioPage = $variables['page']['content'];
        $variables['page']['content'] = [
          '#type' => 'component',
          '#component' => 'aculta420_v2:studio-shell',
          '#props' => [],
          '#slots' => ['content' => $studioPage],
        ];
      }
      $variables['#attached']['library'][] = 'aculta420_v2/account-dialogs';
    }
    $wikiNode = $this->currentRouteMatch?->getParameter('node');
    // ADR-054 P7: CSS editorial de eventos só em suas rotas e nodes.
    if (str_starts_with($routeName, 'aculta_events.')
      || ($wikiNode instanceof NodeInterface && $wikiNode->bundle() === 'aculta_event')) {
      $variables['#attached']['library'][] = 'aculta420_v2/events';
    }
    if (in_array($routeName, self::EVENT_ORGANIZATION_ROUTES, TRUE)) {
      $variables['#attached']['library'][] = 'aculta420_v2/event-organization';
    }
    if ($this->currentRouteMatch?->getRouteName() === 'aculta_user.perfil_publico'
      && isset($variables['page']['content'])
      && is_array($variables['page']['content'])
      && $this->componentAvailable('public-profile-page')) {
      $this->wrapPortalUiRenderArray($variables['page']['content'], 'public-profile', 'public-profile-page');
    }
    $variables['aculta_component_library_page'] = ComponentLibraryRoutes::isComponentLibraryRoute($this->currentRouteMatch?->getRouteName());
    $variables['aculta_lms_lesson_route'] = $this->currentRouteMatch?->getRouteName() === 'aculta_lms.lesson_view';
    $variables['aculta_lms_student_dashboard_route'] = $this->currentRouteMatch?->getRouteName() === 'aculta_lms.student_dashboard';
    $variables['aculta_layout_page_sdc_available'] = $this->componentAvailable('layout-page');
    $variables['aculta_lms_lesson_sdc_available'] = $this->componentAvailable('lms-lesson-page');
    $variables['aculta_page_title'] = [];
    $variables['aculta_breadcrumb'] = [];
    $variables['aculta_help'] = [];
    $variables['aculta_portal_menu'] = [];
    $variables['aculta_account_authenticated'] = $this->currentUser->isAuthenticated();
    $variables['aculta_account_avatar'] = $variables['aculta_account_avatar'] ?? NULL;
    $variables['aculta_account_name'] = $variables['aculta_account_name'] ?? NULL;
    $this->composeDomainHeader($variables);
    if ($variables['aculta_shell_variant'] === 'v2') {
      $this->composeShellTitle($variables);
    }
    if ($variables['aculta_lms_lesson_route']) {
      // The LMS render array owns the lesson H1; avoid a second route-title H1.
      $variables['aculta_page_title'] = [];
    }
    if ($variables['aculta_lms_student_dashboard_route']) {
      // The dashboard SDC owns the page title and its only H1.
      $variables['aculta_page_title'] = [];
    }
    if (str_starts_with($routeName, 'view.aculta_forum_public_topics.')) {
      // O cabeçalho Growroom420 (SDC heading) possui o H1 da listagem do fórum;
      // sem isso a rota renderiza um segundo H1 oculto por CSS.
      $variables['aculta_page_title'] = [];
    }
    $variables['aculta_portal_menu'] = $this->buildAccountMenuComponent(
      $variables['aculta_portal_menu'],
      $variables['aculta_account_authenticated'],
      $variables['aculta_account_avatar'],
      $variables['aculta_account_name'],
      $variables['aculta_domain_sites'] ?? [],
    );
    $this->removeSearchDialogDependency($variables);

    $links = $variables['domain_presentation']['regions']['showcase'] ?? [];
    if (!is_array($links)) {
      $variables['multidomain_network_links'] = [];
      return;
    }

    $variables['multidomain_network_links'] = array_values(array_filter(
      $links,
      static fn (mixed $link): bool => is_array($link)
        && is_string($link['title'] ?? NULL)
        && $link['title'] !== ''
        && is_string($link['url'] ?? NULL)
        && $link['url'] !== '',
    ));
  }

  /**
   * Returns the configured shell variant, restricted to the supported values.
   */
  private function shellVariant(): string {
    $value = $this->configFactory->get('aculta420_v2.settings')->get('aculta_shell_variant');
    return $value === 'v2' ? 'v2' : 'classic';
  }

  /**
   * Moves a page title block out of legacy regions in the parallel shell.
   *
   * Overrides de Domain Config podem colocar page_title_block em navigation.
   * No shell v2 o H1 pertence à área de conteúdo, junto do layout-page, para
   * não duplicar título nem deixar o H1 fora do landmark principal.
   *
   * @param array<string, mixed> $variables
   *   Page theme variables.
   */
  private function composeShellTitle(array &$variables): void {
    foreach (['navigation', 'highlighted'] as $region) {
      if (!isset($variables['page'][$region]) || !is_array($variables['page'][$region])) {
        continue;
      }
      foreach (Element::children($variables['page'][$region]) as $key) {
        $block = $variables['page'][$region][$key];
        if (!is_array($block) || $this->getPluginId($block) !== 'page_title_block') {
          continue;
        }
        if (empty($variables['aculta_page_title'])) {
          $variables['aculta_page_title'][$key] = $block;
        }
        unset($variables['page'][$region][$key]);
      }
    }
  }

  /**
   * Exposes whether a SDC is registered, not merely present as a source file.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $variables['aculta_accordion_sdc_available'] = $this->componentAvailable('accordion');
    if (($variables['node']?->bundle() ?? NULL) === 'revista'
      && ($variables['view_mode'] ?? NULL) === 'teaser') {
      $config = $this->configFactory->get('aculta420_v2.settings');
      $selected = $config->get('aculta_revista_accent.selected.revista_accent');
      $accent = match ($selected) {
        'revista-teaser--accent-green' => 'green',
        'revista-teaser--accent-yellow' => 'yellow',
        'revista-teaser--accent-red' => 'red',
        default => NULL,
      };
      $variables['aculta_revista_accent'] = $accent;
      if ($accent !== NULL) {
        $attributes = $variables['attributes'] ?? new Attribute();
        if (!$attributes instanceof Attribute) {
          $attributes = new Attribute((array) $attributes);
        }
        $attributes->addClass('revista-teaser--accent-' . $accent);
        $variables['attributes'] = $attributes;
      }
      if (isset($variables['elements']) && is_array($variables['elements'])) {
        CacheableMetadata::createFromRenderArray($variables['elements'])
          ->addCacheableDependency($config)
          ->applyTo($variables['elements']);
      }
    }
  }

  /**
   * Maps compatible Portal components to V2 SDCs.
   *
   * The Portal fallback preprocess runs before theme preprocess and fills a
   * generic render array. Replace supported names with their matching SDCs
   * while preserving owner render arrays. `context-tabs` maps to `tabs`;
   * `profile-identity` maps to the `profile_full` variant of `author-card`.
   */
  #[Hook('preprocess_aculta_portal_component')]
  public function preprocessPortalComponent(array &$variables): void {
    $name = $variables['name'] ?? NULL;
    $component = match ($name) {
      'empty-state' => 'empty-state',
      'context-tabs' => 'tabs',
      'lms-learning-journey' => 'lms-learning-journey',
      'profile-identity' => 'author-card',
      'profile-bio', 'profile-social' => 'profile-details',
      'profile-editor' => 'studio-profile-editor',
      default => NULL,
    };
    if ($component === NULL || !$this->componentAvailable($component)) {
      return;
    }

    $props = is_array($variables['props'] ?? NULL) ? $variables['props'] : [];
    $slots = is_array($variables['slots'] ?? NULL) ? $variables['slots'] : [];
    if ($component === 'tabs' && (!is_string($props['label'] ?? NULL) || trim($props['label']) === '' || !isset($slots['items']))) {
      return;
    }
    if ($component === 'profile-details') {
      if (!is_string($props['title'] ?? NULL) || trim($props['title']) === '') {
        return;
      }
      $titleId = $name === 'profile-bio' ? 'studio-profile-bio-title' : 'studio-profile-social-title';
      $contentSlot = $name === 'profile-bio' ? 'bio' : 'social_links';
      $sourceSlot = $name === 'profile-bio' ? 'body' : 'items';
      if (!isset($slots[$sourceSlot])) {
        return;
      }
      $mappedSlots = [$contentSlot => $slots[$sourceSlot]];
      if (isset($slots['actions'])) {
        $mappedSlots['actions'] = $slots['actions'];
      }
      $props = [
        'title' => $props['title'],
        'title_id' => $titleId,
        'heading_level' => 2,
      ];
      $slots = $mappedSlots;
    }

    $attributes = [];
    if (in_array($component, ['author-card', 'studio-profile-editor'], TRUE)) {
      $attributes = $variables['attributes'] ?? [];
      if ($attributes instanceof Attribute) {
        $attributes = $attributes->toArray();
      }
      if (!is_array($attributes)) {
        $attributes = [];
      }
    }
    if ($component === 'author-card') {
      if (!is_string($props['display_name'] ?? NULL) || trim($props['display_name']) === '') {
        return;
      }
      $actions = [];
      if (isset($slots['actions'])) {
        $actions['edit_actions'] = $slots['actions'];
      }
      if (isset($slots['public_link'])) {
        $actions['public_link'] = $slots['public_link'];
      }
      $slots = [];
      if (isset($variables['slots']['avatar'])) {
        $slots['avatar'] = $variables['slots']['avatar'];
      }
      if ($actions !== []) {
        $slots['actions'] = ['#type' => 'container', '#attributes' => ['class' => ['author-card__actions-list']], ...$actions];
      }
      $props = [
        'variant' => 'profile_full',
        'size' => 'md',
        'display_name' => $props['display_name'],
        'designation' => is_string($props['hint'] ?? NULL) ? $props['hint'] : NULL,
        'has_avatar' => isset($slots['avatar']),
        'has_actions' => $actions !== [],
      ];
    }

    $renderArray = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:' . $component,
      '#props' => $props,
      '#slots' => $slots,
    ];
    if (in_array($component, ['author-card', 'studio-profile-editor'], TRUE) && $attributes !== []) {
      $renderArray['#attributes'] = $attributes;
    }
    if ($component === 'studio-profile-editor' && is_array($variables['elements'] ?? NULL)) {
      CacheableMetadata::createFromRenderArray($variables['elements'])->applyTo($renderArray);
      if (is_array($variables['elements']['#attached'] ?? NULL)) {
        $renderArray['#attached'] = $variables['elements']['#attached'];
      }
    }
    $variables['component'] = $renderArray;
  }

  /**
   * Checks the component repository without rebuilding its persistent cache.
   */
  private function componentAvailable(string $component): bool {
    return $this->componentManager?->hasDefinition('aculta420_v2:' . $component) ?? FALSE;
  }

  /**
   * Wraps a named Portal UI render array in its theme-owned SDC.
   *
   * @param array<string|int, mixed> $element
   *   Render tree to search and update.
   * @param string $token
   *   Exact `data-portal-ui` token identifying the component root.
   * @param string $component
   *   Theme SDC machine name.
   *
   * @return bool
   *   Whether a matching render array was wrapped.
   */
  private function wrapPortalUiRenderArray(array &$element, string $token, string $component): bool {
    $attributes = $element['#attributes'] ?? [];
    if ($attributes instanceof Attribute) {
      $attributes = $attributes->toArray();
    }
    $tokens = $attributes['data-portal-ui'] ?? '';
    if (is_string($tokens) && in_array($token, preg_split('/\s+/', trim($tokens)) ?: [], TRUE)) {
      $content = $element;
      $element = [
        '#type' => 'component',
        '#component' => 'aculta420_v2:' . $component,
        '#props' => [],
        '#slots' => ['content' => $content],
      ];
      return TRUE;
    }

    foreach (Element::children($element) as $key) {
      if (is_array($element[$key]) && $this->wrapPortalUiRenderArray($element[$key], $token, $component)) {
        return TRUE;
      }
    }
    if (isset($element['#slots']) && is_array($element['#slots'])) {
      foreach ($element['#slots'] as &$slot) {
        if (is_array($slot) && $this->wrapPortalUiRenderArray($slot, $token, $component)) {
          unset($slot);
          return TRUE;
        }
      }
      unset($slot);
    }
    return FALSE;
  }

  /**
   * Wraps the editorial node form when it is rendered in the Studio.
   *
   * @param array<string|int, mixed> $element
   *   Render tree to search and update.
   */
  private function wrapStudioRevistaForm(array &$element): void {
    if (($element['#form_id'] ?? NULL) === 'node_revista_form') {
      $content = $element;
      $element = [
        '#type' => 'component',
        '#component' => 'aculta420_v2:studio-revista-form',
        '#props' => [],
        '#slots' => ['content' => $content],
      ];
      return;
    }

    foreach (Element::children($element) as $key) {
      if (is_array($element[$key])) {
        $this->wrapStudioRevistaForm($element[$key]);
      }
    }
  }

  /**
   * Wraps Form API forms below an explicitly marked Studio main region.
   *
   * @param array<string|int, mixed> $element
   *   Render tree to search and update.
   * @param bool $insideStudioMain
   *   Whether the current render array descends from the Studio main region.
   * @param bool $insideRevistaForm
   *   Whether the form is already owned by the editorial form component.
   */
  private function wrapStudioForms(
    array &$element,
    bool $insideStudioMain = FALSE,
    bool $insideRevistaForm = FALSE,
  ): void {
    $attributes = $element['#attributes'] ?? [];
    if ($attributes instanceof Attribute) {
      $attributes = $attributes->toArray();
    }
    $tokens = is_array($attributes) && is_string($attributes['data-portal-ui'] ?? NULL)
      ? preg_split('/\s+/', trim($attributes['data-portal-ui'])) ?: []
      : [];
    $insideStudioMain = $insideStudioMain || in_array('studio-main', $tokens, TRUE);
    $insideRevistaForm = $insideRevistaForm
      || ($element['#component'] ?? NULL) === 'aculta420_v2:studio-revista-form';

    if (($element['#type'] ?? NULL) === 'form' && $insideStudioMain && !$insideRevistaForm) {
      $content = $element;
      $element = [
        '#type' => 'component',
        '#component' => 'aculta420_v2:studio-form',
        '#props' => [],
        '#slots' => ['content' => $content],
      ];
      return;
    }

    foreach (Element::children($element) as $key) {
      if (is_array($element[$key])) {
        $this->wrapStudioForms($element[$key], $insideStudioMain, $insideRevistaForm);
      }
    }
    if (isset($element['#slots']) && is_array($element['#slots'])) {
      foreach ($element['#slots'] as &$slot) {
        if (is_array($slot)) {
          $this->wrapStudioForms($slot, $insideStudioMain, $insideRevistaForm);
        }
      }
      unset($slot);
    }
  }

  /**
   * Selects the layout from the route's content type or named public route.
   *
   * Hostname and Portal purpose are deliberately not used for this mapping.
   */
  private function getPageLayoutVariant(): string {
    // Compiled hook discovery can call this class with the previous argument
    // list until Drupal rebuilds its container. Keep that state safe.
    if ($this->currentRouteMatch === NULL || $this->pathMatcher === NULL) {
      return 'stack';
    }

    $routeName = $this->currentRouteMatch->getRouteName();
    if (ComponentLibraryRoutes::isComponentLibraryRoute($routeName)) {
      return 'full';
    }
    if ($routeName === 'aculta_studio.dashboard') {
      return 'aside';
    }

    // A raiz do domínio da Conta é `/conta` (`system.site.page.front` na
    // coleção do Domain). A variante, porém, segue a rota: Minha Conta é
    // `aside` no desktop e o container centralizado vale em todo o domínio.
    if ($this->pathMatcher->isFrontPage() && !str_starts_with((string) $routeName, 'aculta_studio.')) {
      return 'full';
    }

    // Lista pública de tópicos do fórum: largura total, sem o teto de leitura.
    if (str_starts_with((string) $routeName, 'view.aculta_forum_public_topics.')
      || str_starts_with((string) $routeName, 'view.aculta_forum_categories.')) {
      return 'full';
    }
    // Páginas do fórum (tópico, criação de tópico) usam o mesmo container da lista.
    $formNodeType = $this->currentRouteMatch->getParameter('node_type');
    if ($routeName === 'node.add' && is_object($formNodeType) && $formNodeType->id() === 'aculta_forum_topic') {
      return 'full';
    }

    $node = $this->currentRouteMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      return match ($node->bundle()) {
        'wiki_entry' => 'aside',
        'aculta_forum_topic' => 'full',
        'revista' => 'full',
        default => 'stack',
      };
    }

    return match ($this->currentRouteMatch->getRouteName()) {
      'aculta_studio.dashboard',
      'aculta_studio.account',
      'aculta_studio.account_data',
      'aculta_studio.account_address',
      'aculta_studio.account_documents',
      'aculta_studio.account_security',
      'aculta_studio.account_password',
      'aculta_studio.account_connections',
      'aculta_studio.account_orders',
      'aculta_studio.account_support',
      'aculta_studio.account_preferences',
      'aculta_studio.we_sativa',
      'aculta_studio.we_sativa_start',
      'aculta_lms.educator_dashboard',
      'aculta_events.organizer_dashboard' => 'aside',
      'aculta_lms.student_dashboard' => 'full',
      'aculta_lms.lesson_view' => 'aside',
      'aculta_events.catalogue', 'aculta_events.map' => 'full',
      default => 'stack',
    };
  }

  /**
   * Creates the account SDC render array with explicit slots and user context.
   *
   * @param array<string, mixed> $menuItems
   *   Access-aware account menu supplied by Drupal.
   * @param bool $authenticated
   *   Whether the current visitor is authenticated.
   * @param array<string, mixed>|null $avatar
   *   Portal-owned avatar render array.
   * @param string|null $accountName
   *   Display name prepared by the Portal.
   * @param mixed $domainSites
   *   Site links prepared by the Portal for each published purpose.
   *
   * @return array<string, mixed>
   *   Account-menu component render array.
   */
  private function buildAccountMenuComponent(
    array $menuItems,
    bool $authenticated,
    ?array $avatar,
    ?string $accountName,
    mixed $domainSites,
  ): array {
    $slots = [];
    if ($authenticated) {
      if ($menuItems !== []) {
        $slots['menu_items'] = $menuItems;
      }
      if ($avatar !== NULL) {
        $slots['avatar'] = $avatar;
      }
    }
    else {
      $slots['menu_items'] = [
        'entrar' => [
          '#type' => 'link',
          '#title' => 'Entrar',
          '#url' => Url::fromUserInput('/entrar/janela'),
        ],
        'criar_conta' => [
          '#type' => 'link',
          '#title' => 'Criar conta',
          '#url' => Url::fromUserInput('/criar-conta/janela'),
        ],
      ];
    }

    // Links legais apontam só para rotas confirmadas no código e no site publicado.
    $legalLinks = [
      ['title' => 'Busca', 'url' => Url::fromUserInput('/busca')->toString()],
      ['title' => 'Política de privacidade', 'url' => Url::fromUserInput('/politica-de-privacidade')->toString()],
    ];
    if ($authenticated) {
      $legalLinks[] = ['title' => 'Configurações de privacidade', 'url' => Url::fromUserInput('/configuracoes/privacidade')->toString()];
    }

    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:account-menu',
      '#props' => [
        'authenticated' => $authenticated,
        'account_name' => $authenticated && is_string($accountName) && trim($accountName) !== ''
          ? trim($accountName)
          : NULL,
        'sites' => $this->normalizeDomainSites($domainSites),
        'legal_links' => $legalLinks,
      ],
      '#slots' => $slots,
      '#cache' => ['contexts' => ['user']],
    ];
  }

  /**
   * Restricts the Portal sites contract to the fields consumed by the SDC.
   *
   * @param mixed $sites
   *   Candidate sites supplied by ACULTA Portal.
   *
   * @return array
   *   Valid absolute HTTP(S) site links.
   */
  private function normalizeDomainSites(mixed $sites): array {
    if (!is_array($sites)) {
      return [];
    }
    $normalized = [];
    foreach ($sites as $site) {
      if (!is_array($site)
        || !is_string($site['site_key'] ?? NULL)
        || trim($site['site_key']) === ''
        || !is_string($site['title'] ?? NULL)
        || trim($site['title']) === ''
        || !is_string($site['url'] ?? NULL)
        || !in_array(parse_url($site['url'], PHP_URL_SCHEME), ['http', 'https'], TRUE)
        || !is_string(parse_url($site['url'], PHP_URL_HOST))
        || !is_bool($site['current'] ?? NULL)) {
        continue;
      }
      $normalized[] = [
        'purpose' => trim($site['site_key']),
        'title' => trim($site['title']),
        'url' => $site['url'],
        'current' => $site['current'],
      ];
    }
    return $normalized;
  }

  /**
   * The V2 bar uses Fetch instead of Drupal's jQuery dialog.
   *
   * @param array<string, mixed> $variables
   *   Page theme variables.
   */
  private function removeSearchDialogDependency(array &$variables): void {
    if (!isset($variables['aculta_search_utility']['#attached']['library'])
      || !is_array($variables['aculta_search_utility']['#attached']['library'])) {
      return;
    }
    $variables['aculta_search_utility']['#attached']['library'] = array_values(array_filter(
      $variables['aculta_search_utility']['#attached']['library'],
      static fn (string $library): bool => $library !== 'core/drupal.dialog.ajax',
    ));
  }

  /**
   * Moves the access-aware account menu render array into the fixed bar.
   *
   * This preserves the Portal-owned links, access results and cache metadata.
   *
   * @param array<string, mixed> $variables
   *   Page theme variables.
   */
  private function composeDomainHeader(array &$variables): void {
    if (!isset($variables['page']['header']) || !is_array($variables['page']['header'])) {
      return;
    }

    $brand = NULL;
    $brand_key = NULL;
    $menu = NULL;
    $menu_key = NULL;
    $weights = [];
    foreach (Element::children($variables['page']['header']) as $key) {
      $block = $variables['page']['header'][$key];
      if (!is_array($block)) {
        continue;
      }
      $pluginId = $this->getPluginId($block);
      switch ($pluginId) {
        case 'page_title_block':
          $variables['aculta_page_title'][$key] = $block;
          unset($variables['page']['header'][$key]);
          break;

        case 'system_breadcrumb_block':
          $variables['aculta_breadcrumb'][$key] = $block;
          unset($variables['page']['header'][$key]);
          break;

        case 'help_block':
          $variables['aculta_help'][$key] = $block;
          unset($variables['page']['header'][$key]);
          break;

        case 'system_menu_block:account':
          if ($this->currentUser->isAuthenticated()) {
            $variables['aculta_portal_menu'][$key] = $block;
          }
          unset($variables['page']['header'][$key]);
          break;

        case 'system_branding_block':
          $brand = $block;
          $brand_key = $key;
          $weights[] = (int) ($block['#weight'] ?? 0);
          break;

        case 'system_menu_block:main':
          $menu = $block;
          $menu_key = $key;
          $weights[] = (int) ($block['#weight'] ?? 0);
          break;
      }
    }

    // Wiki (e demais purposes sem menu próprio): o menu do purpose principal não é reproduzido.
    if (($variables['domain_presentation']['layout']['show_site_menu'] ?? TRUE) === FALSE && $menu_key !== NULL) {
      unset($variables['page']['header'][$menu_key]);
      $menu = NULL;
      $menu_key = NULL;
    }

    $brandMedia = $variables['domain_presentation']['regions']['brand_media'] ?? NULL;
    $brandProps = is_array($brandMedia) ? $this->buildBrandMediaProps($brandMedia, $variables) : NULL;
    if ($brandProps !== NULL) {
      $brand = NULL;
      $brand_key = $brand_key ?? $menu_key;
      if ($brand_key !== NULL) {
        unset($variables['page']['header'][$brand_key]);
      }
    }

    $targetKey = $menu_key ?? $brand_key;
    if ($targetKey === NULL && $brandProps !== NULL) {
      $targetKey = 'aculta_domain_header';
    }
    if ($targetKey === NULL || ($brand === NULL && $menu === NULL && $brandProps === NULL)) {
      return;
    }

    if ($brand_key !== NULL && $brand_key !== $targetKey) {
      unset($variables['page']['header'][$brand_key]);
    }
    if ($menu_key !== NULL && $menu_key !== $targetKey) {
      unset($variables['page']['header'][$menu_key]);
    }

    // Área da conta: a faixa de logo e menu do domínio é removida; permanece só a barra institucional.
    if (!empty($variables['domain_presentation']['layout']['institution_bar_account'])) {
      unset($variables['page']['header'][$targetKey]);
      return;
    }

    $slots = [];
    if ($brand !== NULL) {
      $slots['brand'] = $brand;
    }
    // Wiki: a busca fica no cabeçalho, entre a marca e o menu (como no aculta420 v1).
    $wikiSearch = $variables['domain_presentation']['regions']['wiki_search'] ?? NULL;
    if ($wikiSearch !== NULL && $wikiSearch !== []) {
      $slots['wiki_search'] = $wikiSearch;
    }
    if ($menu !== NULL) {
      $slots['menu_items'] = $menu;
    }
    $component = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:navigation-bar',
      '#props' => $brandProps ?? [],
      '#slots' => $slots,
    ];
    if ($weights !== []) {
      $component['#weight'] = min($weights);
    }
    $variables['page']['header'][$targetKey] = $component;
  }

  /**
   * Maps a neutral Portal brand key to a theme-owned responsive logo asset.
   *
   * @param array<string, mixed> $brandMedia
   *   Brand identity metadata prepared by ACULTA Portal.
   * @param array<string, mixed> $variables
   *   Page theme variables containing the neutral domain identity.
   *
   * @return array<string, string>|null
   *   Strict SDC props for a supported logo, or NULL for the default branding.
   */
  private function buildBrandMediaProps(array $brandMedia, array $variables): ?array {
    return (new WikiBrandMediaProps($this->themes))->build(
      $brandMedia,
      $variables['domain_presentation']['identity'] ?? [],
    );
  }

  /**
   * Resolves a block plugin ID from a render array or its lazy builder.
   *
   * @param array<string, mixed> $block
   *   The block render array.
   *
   * @return string
   *   The configured block plugin ID, or an empty string.
   */
  private function getPluginId(array $block): string {
    $pluginId = $block['#plugin_id'] ?? '';
    $lazyBuilder = $block['#lazy_builder'] ?? [];
    if ($pluginId === '' && ($lazyBuilder[0] ?? '') === 'Drupal\\block\\BlockViewBuilder::lazyBuilder') {
      $blockId = $lazyBuilder[1][0] ?? '';
      if (is_string($blockId) && $blockId !== '') {
        $pluginId = (string) ($this->configFactory->get('block.block.' . $blockId)->get('plugin') ?? '');
      }
    }

    return is_string($pluginId) ? $pluginId : '';
  }

}

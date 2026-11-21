<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\aculta_portal\Account\StreetNumberField;
use Drupal\node\NodeInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\aculta_portal\EventSubscriber\AccountRouteSubscriber;
use Drupal\aculta_portal\Account\EmailConfirmationNotice;
use Drupal\aculta_portal\Cart\CartUtility;
use Drupal\aculta_portal\Search\SearchUtility;
use Drupal\Core\Template\Attribute;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_portal\AccountShellBuilder;
use Drupal\aculta_portal\Auth\AuthIntegrationManager;
use Drupal\aculta_portal\Domain\AcultaBreadcrumbBuilder;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\aculta_portal\Domain\DomainRoutePolicy;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\Component\Serialization\Json;
use Drupal\aculta_portal\Form\LoginPopupForm;
use Drupal\aculta_portal\Presentation\DomainPresentationBuilder;
use Drupal\block_content\BlockContentInterface;
use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Drupal hooks for the ACULTA account portal.
 */
final class PortalHooks {

  /**
   * Rotas de autenticação e conta do Core (user.*), sempre com noindex.
   */
  private const AUTH_ROUTES = [
    'user.login',
    'user.login_status.http',
    'user.logout',
    'user.logout.confirm',
    'user.logout.http',
    'user.register',
    'user.pass',
    'user.reset',
    'user.reset.form',
    'user.reset.login',
    'user.edit',
    'user.page',
  ];

  private const PUBLIC_PORTAL_ROUTES = [
    'aculta_portal.support_form',
    'aculta_portal.wiki_home',
    'aculta_portal.courses_home',
    'aculta_portal.shop_home',
  ];

  public function __construct(
    #[Autowire(service: 'current_route_match')]
    private readonly CurrentRouteMatch $routeMatch,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'path.current')]
    private readonly CurrentPathStack $currentPath,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
    #[Autowire(service: 'module_handler')]
    private readonly ModuleHandlerInterface $moduleHandler,
    #[Autowire(service: 'plugin.manager.block')]
    private readonly BlockManagerInterface $blockManager,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'aculta_portal.search_utility')]
    private readonly SearchUtility $searchUtility,
    #[Autowire(service: 'aculta_portal.cart_utility')]
    private readonly CartUtility $cartUtility,
    #[Autowire(service: 'extension.list.module')]
    private readonly ModuleExtensionList $moduleList,
    #[Autowire(service: 'aculta_portal.domain_site')]
    private readonly DomainSiteManagerInterface $domainSiteManager,
    #[Autowire(service: 'aculta_portal.auth_integration')]
    private readonly AuthIntegrationManager $authIntegrationManager,
    #[Autowire(service: 'aculta_portal.account_shell_builder')]
    private readonly AccountShellBuilder $accountShellBuilder,
    #[Autowire(service: 'aculta_portal.email_confirmation_notice')]
    private readonly EmailConfirmationNotice $emailConfirmationNotice,
    #[Autowire(service: 'aculta_portal.breadcrumb_builder')]
    private readonly AcultaBreadcrumbBuilder $breadcrumbBuilder,
    #[Autowire(service: 'aculta_portal.presentation.domain')]
    private readonly DomainPresentationBuilder $domainPresentationBuilder,
    #[Autowire(service: 'renderer')]
    private readonly RendererInterface $renderer,
  ) {}

  /**
 * Implements hook_contextual_links_view_alter().
 *
 * @param array<array-key, mixed> $element
 *   Field element.
 * @param array<array-key, mixed> $items
 *   Field item list.
 */
  #[Hook('contextual_links_view_alter')]
  public function contextualLinksViewAlter(array &$element, array $items): void {
    // A interface administrativa vive no MAIN; nos subdomínios não há links
    // contextuais de edição.
    if ($this->domainSiteManager->getCurrentSiteKey()?->value !== SiteKey::Main->value) {
      $element['#links'] = [];
      $element['#access'] = FALSE;
    }
  }

  /**
 * Implements hook_preprocess_page().
 *
 * @param array<mixed> $variables
 *   Template variables.
 */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $domainPresentation = $this->domainPresentationBuilder->buildCurrent();
    $presentation = $domainPresentation?->toThemeArray();
    $variables['domain_presentation'] = $presentation;
    $variables['aculta_domain_sites'] = $presentation['domain_sites'] ?? [];
    $variables['aculta_search_utility'] = $this->searchUtility->build();
    // Ícone da ACULTA na barra multidomínio: página inicial do domínio
    // principal, nunca fixa no código.
    $mainHome = $this->domainSiteManager->pathUrl(SiteKey::Main, '/');
    $variables['aculta_brand_home'] = $mainHome !== NULL ? $mainHome->toString() : '/';
    $cart = $this->cartUtility->build();
    /** @var array<string, mixed> $page */
    $page = isset($variables['page']) && is_array($variables['page']) ? $variables['page'] : [];
    if (isset($page['#type']) || isset($page['content']) || $page !== []) {
      $this->renderer->addCacheableDependency($page, $cart['cache']);
    }
    unset($cart['cache']);
    $variables['aculta_cart'] = $cart;
    if ($domainPresentation !== NULL && $page !== []) {
      $this->renderer->addCacheableDependency($page, $domainPresentation);
    }
    $variables['page'] = $page;
    $account = $this->currentUser;
    $path = $this->currentPath->getPath();
    $request = $this->requestStack->getCurrentRequest();
    if ($request !== NULL
      && $account->isAuthenticated()
      && !$account->hasPermission('administer users')
      && preg_match('#^/user/([1-9][0-9]*)/edit$#D', $path, $matches)) {
      $target_uid = (int) $matches[1];
      if ($target_uid === (int) $account->id()
        && !AccountRouteSubscriber::isValidCorePasswordResetRequest($request, $target_uid, (int) $account->id())) {
        $variables['aculta_account_edit_blocked'] = TRUE;
        $variables['aculta_account_security_url'] = Url::fromRoute('aculta_portal.security')->toString();
      }
    }

    $rawVars = &$variables;
    $this->accountShellBuilder->build($rawVars);

    $page = is_array($variables['page'] ?? NULL) ? $variables['page'] : [];
    $pageContent = is_array($page['content'] ?? NULL) ? $page['content'] : [];
    if (($pageContent['#theme'] ?? NULL) === 'portal_shell'
      && isset($page['header'])
      && is_array($page['header'])) {
      /** @var array<string, mixed> $header */
      $header = $page['header'];
      $this->removeHeaderBlockByPlugin($header, 'page_title_block');
      $page['header'] = $header;
    }

    // Breadcrumb do bloco de layout: todos os subdomínios e módulos, exceto LinkWeed.
    if (isset($page['header']) && is_array($page['header'])
      && $this->breadcrumbExcluido()) {
      /** @var array<string, mixed> $header */
      $header = $page['header'];
      $this->removeHeaderBlockByPlugin($header, 'system_breadcrumb_block');
      $page['header'] = $header;
    }

    // Aviso de e-mail não confirmado em "Minha conta" (não descartável):
    // acima do conteúdo da página.
    if (($notice = $this->emailConfirmationNotice->build()) !== NULL && isset($page['content'])) {
      $page['content'] = [
        '#type' => 'container',
        'aculta_email_notice' => $notice,
        'body' => $page['content'],
      ];
    }
    $variables['page'] = $page;
  }

  /**
   * Indica se o breadcrumb fica fora da página: LinkWeed (subdomínio links e rotas aculta_linkweed.*).
   */
  private function breadcrumbExcluido(): bool {
    if ($this->domainSiteManager->getCurrentSiteKey() === SiteKey::Links) {
      return TRUE;
    }
    return str_starts_with((string) $this->routeMatch->getRouteName(), 'aculta_linkweed.');
  }

  /**
   * Removes a header block owned by the Portal shell before theme preprocess.
   *
   * @param array<mixed> $header
   *   Value used for $header.
   * @param string $pluginId
   *   Value used for $pluginId.
   */
  private function removeHeaderBlockByPlugin(array &$header, string $pluginId): void {
    foreach (Element::children($header) as $key) {
      $childKey = is_scalar($key) ? (string) $key : '';
      if ($childKey === '') {
        continue;
      }
      $block = is_array($header[$childKey] ?? NULL) ? $header[$childKey] : [];
      $candidate = isset($block['#plugin_id']) && is_scalar($block['#plugin_id']) ? (string) $block['#plugin_id'] : '';
      $lazyBuilder = is_array($block['#lazy_builder'] ?? NULL) ? $block['#lazy_builder'] : [];
      if ($candidate === ''
        && ($lazyBuilder[0] ?? '') === 'Drupal\\block\\BlockViewBuilder::lazyBuilder') {
        $lazyArgs = is_array($lazyBuilder[1] ?? NULL) ? $lazyBuilder[1] : [];
        $blockId = is_scalar($lazyArgs[0] ?? NULL) ? (string) $lazyArgs[0] : '';
        if ($blockId !== '') {
          $pluginCfg = $this->configFactory->get('block.block.' . $blockId)->get('plugin');
          $candidate = is_scalar($pluginCfg) ? (string) $pluginCfg : '';
        }
      }
      if ($candidate === $pluginId) {
        unset($header[$childKey]);
      }
    }
  }

  /**
   * Supplies presentation-only data for the institutional content block.
   */

  /**
 * Implements hook_preprocess_aculta_portal_element(). Os elementos de presentation (ex.: busca da Wiki) chegam ao tema como array, sem objetos. O atributo é montado aqui, no último passo antes do template, que espera um Attribute.
 *
 * @param array<array-key, mixed> $variables
 *   Template variables.
 */
  #[Hook('preprocess_aculta_portal_element')]
  public function preprocessPortalElement(array &$variables): void {
    if (is_array($variables['element_attributes'] ?? NULL)) {
      $variables['element_attributes'] = new Attribute($variables['element_attributes']);
    }
  }

  /**
 * Adds institution content to the block template variables.
 *
 * @param array<array-key, mixed> $variables
 *   Template variables.
 */
  #[Hook('preprocess_block')]
  public function preprocessBlock(array &$variables): void {
    $elements = is_array($variables['elements'] ?? NULL) ? $variables['elements'] : [];
    $content = is_array($elements['content'] ?? NULL) ? $elements['content'] : [];
    $entity = $content['#block_content'] ?? NULL;
    if (!$entity instanceof BlockContentInterface
      || $entity->bundle() !== 'aculta_institution') {
      return;
    }

    $variables['institution_entity'] = $entity;
    $variables['view_mode'] = is_scalar($content['#view_mode'] ?? NULL) ? (string) $content['#view_mode'] : 'full';

    $nidVal = $this->configFactory
      ->get('aculta_portal.settings')
      ->get('institution_transparency_nid');
    $nid = is_numeric($nidVal) ? (int) $nidVal : 0;
    $variables['institution_transparency_url'] = $nid > 0
      ? $this->domainSiteManager->routeUrl(SiteKey::Main, 'entity.node.canonical', ['node' => $nid])
      : NULL;
  }

  /**
 * Supplies the current-page label consumed by the public breadcrumb template.
 *
 * @param array<array-key, mixed> $variables
 *   Template variables.
 */
  #[Hook('preprocess_breadcrumb')]
  public function preprocessBreadcrumb(array &$variables): void {
    $variables['aculta_current_breadcrumb'] = $this->breadcrumbBuilder
      ->currentTitle($this->routeMatch);
    $variables['aculta_breadcrumb_sessao'] = $this->breadcrumbBuilder->sessao();
    // O início da trilha é sempre a página inicial do domínio principal.
    $mainHome = $this->domainSiteManager->pathUrl(SiteKey::Main, '/');
    $variables['aculta_breadcrumb_inicio'] = $mainHome !== NULL ? $mainHome->toString() : '/';
  }

  /**
 * Implements hook_menu_links_discovered_alter().
 *
 * @param array<array-key, mixed> $links
 *   Value used for $links.
 */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(array &$links): void {
    // The portal owns the account landing page, not the generic user profile.
    $userPage = is_array($links['user.page'] ?? NULL) ? $links['user.page'] : [];
    if (($userPage['menu_name'] ?? NULL) === 'account') {
      unset($links['user.page']);
    }
  }

  /**
 * Sends identity, support and editorial links to their canonical domains.
 *
 * @param array<array-key, mixed> $variables
 *   Template variables.
 */
  #[Hook('preprocess_menu')]
  public function preprocessMenu(array &$variables): void {
    if (empty($variables['items']) || !is_array($variables['items'])) {
      return;
    }
    $items = $variables['items'];
    $this->decorateLoginPopupItems($items);
    $this->rewriteDomainMenuItems($items, $this->domainSiteManager);
    $this->markAccountHomeItems($items);
    $variables['items'] = $items;
    if (isset($variables['#cache']) && is_array($variables['#cache'])) {
      /** @var array<array-key, string> $existingContexts */
      $existingContexts = is_array($variables['#cache']['contexts'] ?? NULL) ? $variables['#cache']['contexts'] : [];
      $variables['#cache']['contexts'] = array_values(array_unique(array_merge(
        $existingContexts,
        ['domain', 'url.path', 'url.query_args', 'user.roles'],
      )));
    }
  }

  /**
 * Sends editorial author and category links to MAGAZINE.
 *
 * @param array<array-key, mixed> $variables
 *   Template variables.
 */
  #[Hook('preprocess_links')]
  public function preprocessLinks(array &$variables): void {
    if (!isset($variables['links']) || !is_array($variables['links'])) {
      return;
    }
    $resolver = $this->domainSiteManager;
    foreach ($variables['links'] as &$link) {
      if (!is_array($link)) {
        continue;
      }
      $url = $link['url'] ?? NULL;
      if (!$url instanceof Url || !$url->isRouted()) {
        continue;
      }

      $routeName = $url->getRouteName();
      if (DomainRoutePolicy::isCentralTransactionRouteName($routeName)) {
        /** @var array<string, mixed> $routeParams */
        $routeParams = $url->getRouteParameters();
        $target = $resolver->routeUrl(
          SiteKey::Main,
          $routeName,
          $routeParams,
        );
        if ($target instanceof Url) {
          $options = $url->getOptions();
          if (!empty($options['query']) && is_array($options['query'])) {
            $target->setOption('query', $options['query']);
          }
          $link['url'] = $target;
        }
        continue;
      }

      if ($routeName !== 'entity.taxonomy_term.canonical') {
        continue;
      }
      $params = $url->getRouteParameters();
      $term_id = $params['taxonomy_term'] ?? NULL;
      $term = is_numeric($term_id) ? $this->entityTypeManager->getStorage('taxonomy_term')->load((int) $term_id) : NULL;
      if ($term instanceof TermInterface && in_array($term->bundle(), ['editorial_author', 'editorial_category'], TRUE)) {
        /** @var array<string, mixed> $routeParams */
        $routeParams = ['taxonomy_term' => $term->id()];
        $link['url'] = $resolver->routeUrl(SiteKey::Magazine, 'entity.taxonomy_term.canonical', $routeParams) ?? $url;
      }
    }
    unset($link);
  }

  /**
   * Marks the "Minha conta" link with a semantic token. A domain rewrite does not affect identification by menu link. Highlighting remains a theme decision based on the domain_presentation contract.
   *
   * @param array<array-key, mixed> $items
   *   Field item list.
   */
  private function markAccountHomeItems(array &$items): void {
    foreach ($items as &$item) {
      if (!is_array($item)) {
        continue;
      }
      $link = $item['original_link'] ?? NULL;
      $url = $item['url'] ?? NULL;
      if ($link instanceof MenuLinkInterface && $link->getPluginId() === 'aculta_portal.dashboard' && $url instanceof Url) {
        $attributes = $url->getOption('attributes');
        $attrArr = is_array($attributes) ? $attributes : [];
        $url->setOption('attributes', array_merge($attrArr, ['data-portal-ui' => 'account-home']));
      }
      if (!empty($item['below']) && is_array($item['below'])) {
        $this->markAccountHomeItems($item['below']);
      }
    }
    unset($item);
  }

  /**
   * Decorates anonymous login links to open the same-host modal popup. The /entrar route remains available when JavaScript is disabled.
   *
   * @param array<array-key, mixed> $items
   *   Field item list.
   */
  private function decorateLoginPopupItems(array &$items): void {
    if ($this->currentUser->isAuthenticated()) {
      return;
    }
    $destination = LoginPopupForm::safeDestination($this->requestStack->getCurrentRequest()?->getRequestUri());
    foreach ($items as &$item) {
      if (!is_array($item)) {
        continue;
      }
      $url = $item['url'] ?? NULL;
      if (!$url instanceof Url || !$url->isRouted() || $url->getRouteName() !== 'user.login') {
        continue;
      }
      $popup = Url::fromRoute('aculta_portal.login_popup', [], ['query' => ['destination' => $destination]]);
      $popup->setOption('attributes', [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'title' => (string) $this->translation->translate('Entrar'),
          'closeText' => (string) $this->translation->translate('Fechar'),
        ]),
      ]);
      $item['url'] = $popup;
    }
    unset($item);
  }

  /**
   * Rewrites only known cross-domain links while preserving menu structure.
   *
   * @param array<array-key, mixed> $items
   *   Field item list.
   * @param DomainSiteManagerInterface $resolver
   *   Value used for $resolver.
   */
  private function rewriteDomainMenuItems(array &$items, DomainSiteManagerInterface $resolver): void {
    foreach ($items as &$item) {
      if (!is_array($item)) {
        continue;
      }
      $url = $item['url'] ?? NULL;
      if ($url instanceof Url) {
        $route = $url->isRouted() ? $url->getRouteName() : NULL;
        $uri = $url->isRouted() ? NULL : $url->getUri();
        $target = NULL;
        if (is_string($route) && DomainRoutePolicy::isCentralTransactionRouteName($route)) {
          /** @var array<string, mixed> $routeParams */
          $routeParams = $url->getRouteParameters();
          $target = $resolver->routeUrl(SiteKey::Main, $route, $routeParams);
          $options = $url->getOptions();
          if ($target instanceof Url && !empty($options['query']) && is_array($options['query'])) {
            $target->setOption('query', $options['query']);
          }
        }
        elseif (in_array($route, ['user.login', 'user.register', 'user.pass', 'user.logout'], TRUE)) {
          /** @var array<string, mixed> $routeParams */
          $routeParams = $url->getRouteParameters();
          $target = $resolver->routeUrl(SiteKey::Account, (string) $route, $routeParams);
          if ($route === 'user.login' && !$this->currentUser->isAuthenticated()
            && $this->routeMatch->getRouteName() !== 'user.login') {
            $siteKey = $resolver->getCurrentSiteKey();
            $request = $this->requestStack->getCurrentRequest();
            $path = $request?->getPathInfo() ?? $this->currentPath->getPath();
            $destination = $request?->getRequestUri() ?? $path;
            if ($siteKey !== NULL && str_starts_with($path, '/') && !str_starts_with($path, '//')
              && !preg_match('#^/(?:entrar|oauth|sair|recuperar(?:-senha|-acesso)?)(?:/|$)#', $path)) {
              $target?->setOption('query', [
                'destination' => $destination,
                'aculta_destination_site_key' => $siteKey->value,
              ]);
            }
          }
        }
        elseif ($route === 'aculta_portal.dashboard' || $route === 'user.page') {
          $target = $resolver->pathUrl(SiteKey::Account, '/');
        }
        elseif ($route === 'entity.node.canonical') {
          $parameters = $url->getRouteParameters();
          $node_parameter = $parameters['node'] ?? NULL;
          $node = $node_parameter instanceof
          NodeInterface ? $node_parameter
            : (is_numeric($node_parameter) ? $this->entityTypeManager->getStorage('node')->load((int) $node_parameter) : NULL);
          if ($node instanceof NodeInterface && $node->hasField('field_domain_source') && !$node->get('field_domain_source')->isEmpty()) {
            $sourceField = $node->get('field_domain_source');
            $sourceVal = $sourceField instanceof EntityReferenceFieldItemListInterface ? (string) $sourceField->target_id : '';
            $siteKey = $resolver->getSiteKeyForDomainId($sourceVal);
            if ($siteKey !== NULL) {
              $target = $resolver->routeUrl($siteKey, 'entity.node.canonical', ['node' => $node->id()]);
            }
          }
        }
        elseif ($route === 'aculta_portal.support_form' || in_array($uri, ['internal:/apoio', 'internal:/apoie'], TRUE)) {
          $target = $resolver->pathUrl(SiteKey::Support, '/');
        }
        elseif ($uri === 'internal:/noticias') {
          $target = $resolver->pathUrl(SiteKey::Magazine, '/noticias');
        }
        if ($target instanceof Url) {
          $item['url'] = $target;
        }
      }
      if (!empty($item['below']) && is_array($item['below'])) {
        $this->rewriteDomainMenuItems($item['below'], $resolver);
      }
    }
    unset($item);
  }

  /**
 * Replaces the workflow state with its translated Portal label. The order detail does not expose Commerce's English workflow label.
 *
 * @param array<array-key, mixed> $build
 *   Render array to modify.
 * @param mixed $entity
 *   Value used for $entity.
 * @param mixed $display
 *   Value used for $display.
 */
  #[Hook('entity_view_alter')]
  public function commerceOrderUserView(array &$build, $entity, $display): void {
    if (!$entity instanceof OrderInterface) {
      return;
    }
    if (!is_object($display) || !method_exists($display, 'id') || $display->id() !== 'commerce_order.default.user' || !isset($build['state']) || !is_array($build['state'])) {
      return;
    }
    $label = match ($entity->getState()->getId()) {
      'completed' => (string) $this->translation->translate('Concluído'),
      'canceled' => (string) $this->translation->translate('Cancelado'),
      'draft' => (string) $this->translation->translate('Rascunho'),
      default => (string) $this->translation->translate('Em andamento'),
    };
    $build['state']['#title'] = $this->translation->translate('Situação');
    $build['state'][0] = ['#plain_text' => $label];
  }

  /**
 * Implements hook_page_attachments().
 *
 * @param array<array-key, mixed> $attachments
 *   Value used for $attachments.
 */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $route = $this->routeMatch->getRouteName();
    $attached = is_array($attachments['#attached'] ?? NULL) ? $attachments['#attached'] : [];
    $library = is_array($attached['library'] ?? NULL) ? $attached['library'] : [];
    // Anonymous popup links keep their fallback for V1; ACULTA420 V2 enhances
    // the login route with HTMX.
    if (!$this->currentUser->isAuthenticated()) {
      $library[] = 'core/drupal.dialog.ajax';
    }
    if (in_array($route, [
      'aculta_portal.dashboard',
      'aculta_portal.my_data',
      'aculta_portal.my_data_address',
      'aculta_portal.my_data_other',
      'aculta_portal.connections',
      'aculta_portal.security',
      'aculta_portal.security_password',
      'aculta_portal.support_my',
      'aculta_portal.account_courses',
      'aculta_portal.account_settings',
    ], TRUE)) {
      $library[] = 'aculta_portal/account-navigation';
    }
    $attached['library'] = $library;
    $attachments['#attached'] = $attached;
  }

  /**
 * Adapts login and Profile forms to the Portal's focused workflows.
 *
 * @param array<array-key, mixed> $form
 *   Form render array.
 * @param FormStateInterface $form_state
 *   Current form state.
 * @param string $form_id
 *   Value used for $form_id.
 */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if ($form_id === 'user_login_form') {
      $nameField = is_array($form['name'] ?? NULL) ? $form['name'] : [];
      $nameField['#title'] = $this->translation->translate('Nome de usuário, e-mail ou CPF');
      $nameField['#description'] = $this->translation->translate('Informe seu e-mail, nome de usuário ou CPF.');
      $form['name'] = $nameField;
      $password_reset_link = PortalLinkElement::build(
        $this->translation->translate('Esqueci minha senha'),
        Url::fromRoute('user.pass'),
        ['data-portal-ui' => 'login-reset-link'],
      );
      $password_reset_link['#weight'] = 10;
      $form['password_reset_link'] = $password_reset_link;
      $form['registration_notice'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'login-registration'],
        'text' => ['#plain_text' => $this->translation->translate('Ainda não tem uma conta?')],
        'link' => [
          '#type' => 'portal_button',
          '#props' => [
            'text' => (string) $this->translation->translate('Ver informações sobre o cadastro'),
            'url' => Url::fromRoute('user.register')->toString(),
            'variant' => 'secondary',
          ],
        ],
        '#weight' => 30,
      ];
    }
    if ($form_id === 'user_login_form'
      && $this->routeMatch->getRouteName() === 'user.login'
      && $this->currentPath->getPath() === '/entrar'
      && $this->moduleHandler->moduleExists('social_auth')
      && $this->authIntegrationManager->isGoogleConfigured()) {
      $form['social_auth_divider'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'login-divider'],
        'line_before' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute(['aria-hidden' => 'true']),
        ],
        'label' => ['#plain_text' => $this->translation->translate('ou')],
        'line_after' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute(['aria-hidden' => 'true']),
        ],
        '#weight' => 20,
      ];
      $socialBlock = $this->blockManager->createInstance('social_auth_login', []);
      $providerBuild = $socialBlock instanceof BlockPluginInterface ? $socialBlock->build() : [];
      $form['social_auth'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'auth-provider'],
        'provider' => $providerBuild,
        '#weight' => 21,
      ];
    }

    $form_object = $form_state->getFormObject();
    if (!method_exists($form_object, 'getEntity')) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity instanceof EntityInterface) {
      return;
    }

    $path = $this->currentPath->getPath();
    $isStudioDataRoute = $this->routeMatch->getRouteName() === 'aculta_studio.account_data';
    // Portal and Studio edit the same canonical Commerce customer address.
    // Recognize both route IDs; legacy form scope and behavior stay unchanged.
    $isAddressRoute = in_array($this->routeMatch->getRouteName(), [
      'aculta_portal.my_data_address',
      'aculta_studio.account_address',
    ], TRUE);
    // /dados/endereco é tratado pelo bloco logo abaixo (país padrão, número e
    // S/N). Este bloco retorna antes e não pode engolir essa página: o tipo
    // authenticated também é tipo de papel.
    if ($entity->getEntityTypeId() === 'profile' && $entity instanceof ProfileInterface && PerfilDoPapel::ehTipoDePapel($entity) && !$isAddressRoute) {
      if ($path === '/dados' || $isStudioDataRoute) {
        // Keep this tab limited to personal basics, even if older active
        // Profile display configuration still contains address components.
        $allowed = [
          'field_first_name',
          'field_last_name',
          'field_whatsapp',
          'actions',
          'form_build_id',
          'form_token',
          'form_id',
        ];
        // The legacy /dados keeps its public nickname control unchanged.
        if (!$isStudioDataRoute) {
          array_unshift($allowed, 'field_nickname');
        }
        foreach (array_keys($form) as $key) {
          if (!str_starts_with((string) $key, '#') && !in_array($key, $allowed, TRUE)) {
            unset($form[$key]);
          }
        }
      }
      else {
        foreach (['field_nickname', 'field_first_name', 'field_last_name', 'field_whatsapp'] as $field) {
          unset($form[$field]);
        }
      }
      if (($path === '/dados' || $isStudioDataRoute) && isset($form['actions']) && is_array($form['actions']) && isset($form['actions']['submit']) && is_array($form['actions']['submit'])) {
        $submit = $form['actions']['submit'];
        $submitHooks = is_array($submit['#submit'] ?? NULL) ? $submit['#submit'] : [];
        $submitHooks[] = 'aculta_portal.form_callbacks:syncCustomerAddressNames';
        if ($isStudioDataRoute) {
          $submitHooks[] = 'aculta_portal.form_callbacks:accountDataRedirect';
        }
        $submit['#submit'] = $submitHooks;
        $form['actions']['submit'] = $submit;
      }
      return;
    }

    // Portal (/dados/endereco) e Conta (/conta/endereco) usam o mesmo
    // editor de endereço.
    if ($entity->getEntityTypeId() === 'profile' && $entity instanceof ProfileInterface && $entity->bundle() === 'authenticated'
      && $isAddressRoute
      && (int) $entity->getOwnerId() === (int) $this->currentUser->id()) {
      // The Portal and Commerce edit the same customer Profile Address.
      $allowed = ['field_address', 'actions', 'form_build_id', 'form_token', 'form_id'];
      foreach (array_keys($form) as $key) {
        if (!str_starts_with((string) $key, '#') && !in_array($key, $allowed, TRUE)) {
          unset($form[$key]);
        }
      }
      if (isset($form['field_address']) && is_array($form['field_address'])) {
        $widget = is_array($form['field_address']['widget'] ?? NULL) ? $form['field_address']['widget'] : [];
        $firstWidget = is_array($widget[0] ?? NULL) ? $widget[0] : [];
        if (isset($firstWidget['address']) && is_array($firstWidget['address'])) {
          $address_element = &$firstWidget['address'];
          $participant = PerfilDoPapel::principal($this->entityTypeManager->getStorage('profile'), $this->currentUser);
          if ($participant instanceof ProfileInterface) {
            $defVal = is_array($address_element['#default_value'] ?? NULL) ? $address_element['#default_value'] : [];
            $firstNameVal = $participant->get('field_first_name')->value;
            $defVal['given_name'] ??= is_scalar($firstNameVal) ? (string) $firstNameVal : '';
            $lastNameVal = $participant->get('field_last_name')->value;
            $defVal['family_name'] ??= is_scalar($lastNameVal) ? (string) $lastNameVal : '';
            $address_element['#default_value'] = $defVal;
          }
          foreach (['given_name', 'additional_name', 'family_name'] as $private_or_duplicate) {
            if (isset($address_element[$private_or_duplicate]) && is_array($address_element[$private_or_duplicate])) {
              $address_element[$private_or_duplicate]['#access'] = FALSE;
            }
          }
          unset($address_element);
        }
        $widget[0] = $firstWidget;
        $form['field_address']['widget'] = $widget;
      }
      // Número e S/N são subcampos logo depois da rua. Os valores vêm do
      // perfil salvo.
      $parts = ['number' => '', 'sn' => FALSE];
      if ($entity->id()) {
        $saved = $this->entityTypeManager->getStorage('profile')->load($entity->id());
        if ($saved instanceof ProfileInterface && $saved->hasField('field_address') && !$saved->get('field_address')->isEmpty()) {
          $addressFirst = $saved->get('field_address')->first();
          $addressValue = $addressFirst ? $addressFirst->getValue() : [];
          if (is_array($addressValue)) {
            $line1Val = $addressValue['address_line1'] ?? '';
            $parts = StreetNumberField::split(is_scalar($line1Val) ? (string) $line1Val : '');
          }
        }
      }
      $fieldAddressForm = is_array($form['field_address'] ?? NULL) ? $form['field_address'] : [];
      $widgetForm = is_array($fieldAddressForm['widget'] ?? NULL) ? $fieldAddressForm['widget'] : [];
      $firstWidgetForm = is_array($widgetForm[0] ?? NULL) ? $widgetForm[0] : [];
      if (isset($firstWidgetForm['address']) && is_array($firstWidgetForm['address'])) {
        $numStr = is_scalar($parts['number']) ? (string) $parts['number'] : '';
        AccountAddressForm::apply($firstWidgetForm['address'], $numStr, (bool) $parts['sn']);
        $widgetForm[0] = $firstWidgetForm;
        $fieldAddressForm['widget'] = $widgetForm;
        $form['field_address'] = $fieldAddressForm;
        $attached = is_array($form['#attached'] ?? NULL) ? $form['#attached'] : [];
        $lib = is_array($attached['library'] ?? NULL) ? $attached['library'] : [];
        $lib[] = AccountAddressForm::LIBRARY;
        $attached['library'] = $lib;
        $form['#attached'] = $attached;
      }
      // Junta rua e número antes do save em buildStreetNumber().
      $builders = is_array($form['#entity_builders'] ?? NULL) ? $form['#entity_builders'] : [];
      $builders[] = 'aculta_portal.form_callbacks:buildStreetNumber';
      $form['#entity_builders'] = $builders;
      if (isset($form['actions']) && is_array($form['actions']) && isset($form['actions']['submit']) && is_array($form['actions']['submit'])) {
        $form['actions']['submit']['#value'] = $this->translation->translate('Salvar endereço');
        $submitHooks = is_array($form['actions']['submit']['#submit'] ?? NULL) ? $form['actions']['submit']['#submit'] : [];
        $submitHooks[] = 'aculta_portal.form_callbacks:addressRedirect';
        $form['actions']['submit']['#submit'] = $submitHooks;
      }
      return;
    }

    // The account overview edits only the current account's photo here.
    if ($entity->getEntityTypeId() === 'user' && $this->routeMatch->getRouteName() === 'aculta_portal.dashboard') {
      $allowed = ['user_picture', 'actions', 'form_build_id', 'form_token', 'form_id'];
      foreach (array_keys($form) as $key) {
        if (!in_array($key, $allowed, TRUE) && !str_starts_with((string) $key, '#')) {
          unset($form[$key]);
        }
      }
      if (isset($form['actions']) && is_array($form['actions']) && isset($form['actions']['submit']) && is_array($form['actions']['submit'])) {
        $form['actions']['submit']['#value'] = $this->translation->translate('Salvar foto do perfil');
        $submitHooks = is_array($form['actions']['submit']['#submit'] ?? NULL) ? $form['actions']['submit']['#submit'] : [];
        $submitHooks[] = 'aculta_portal.form_callbacks:accountPhotoRedirect';
        $form['actions']['submit']['#submit'] = $submitHooks;
      }
    }
  }

  /**
 * Implements hook_page_attachments_alter().
 *
 * @param array<array-key, mixed> $attachments
 *   Value used for $attachments.
 */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    $attached = is_array($attachments['#attached'] ?? NULL) ? $attachments['#attached'] : [];
    $htmlHead = is_array($attached['html_head'] ?? NULL) ? $attached['html_head'] : [];
    foreach ($htmlHead as $delta => $item) {
      if (!is_array($item)) {
        continue;
      }
      $element = is_array($item[0] ?? NULL) ? $item[0] : [];
      $attrs = is_array($element['#attributes'] ?? NULL) ? $element['#attributes'] : [];
      $nameAttr = is_scalar($attrs['name'] ?? NULL) ? (string) $attrs['name'] : '';
      if (($element['#tag'] ?? NULL) === 'meta' && strtolower($nameAttr) === 'generator') {
        unset($htmlHead[$delta]);
      }
    }
    $attached['html_head'] = $htmlHead;
    $attachments['#attached'] = $attached;
  }

  /**
 * Implements hook_theme().
 *
 * @return array<string, mixed>
 *   Render array returned by the method.
 */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'aculta_portal_element' => [
        'variables' => [
          'tag' => 'span',
          'value' => NULL,
          'element_attributes' => NULL,
        ],
        'template' => 'portal-element',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
      // O módulo pede pelo nome; o tema monta o componente SDC.
      'portal_component' => [
        'variables' => [
          'name' => NULL,
          'props' => [],
          'slots' => [],
          'component' => NULL,
          'attributes' => [],
        ],
        'template' => 'portal-component',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
      'portal_shell' => [
        'variables' => [
          'section_title' => NULL,
          'menu_items' => [],
          'accordion_items' => [],
          'logout' => [],
          'content' => NULL,
          'component' => NULL,
        ],
        'template' => 'aculta-portal-shell',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
      'aculta_section' => [
        'variables' => [
          'section_title' => NULL,
          'section_heading' => NULL,
          'section_variant' => 'default',
          'section_color' => 'default',
          'section_body' => NULL,
          'component' => NULL,
        ],
        'template' => 'aculta-section',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
      'portal_photo_editor' => [
        'variables' => [
          'photo' => NULL,
          'photo_form' => NULL,
          'display_name' => NULL,
          'component' => NULL,
        ],
        'template' => 'aculta-portal-photo-editor',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
    ];
  }

  /**
 * Supplies metadata for the public support page and private result routes.
 *
 * @param array<array-key, mixed> $tags
 *   Value used for $tags.
 * @param array<array-key, mixed> $context
 *   Value used for $context.
 */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$tags, array $context): void {
    $route = $this->routeMatch->getRouteName();
    if ($route === 'aculta_portal.support_form') {
      $description = 'Apoie os projetos culturais, ações de formação, comunicação, cuidado e participação social da Associação Cultural Antiproibicionista.';
      $supportUrl = $this->domainSiteManager->routeUrl(SiteKey::Support, '<front>');
      $supportUrl = $supportUrl ? $supportUrl->toString() : '';
      $tags = array_replace($tags, [
        'title' => 'Apoie a Associação | Associação Cultural Antiproibicionista',
        'description' => $description,
        'canonical_url' => $supportUrl,
        'og_title' => 'Apoie a Associação',
        'og_description' => $description,
        'og_url' => $supportUrl,
        'og_type' => 'website',
        'schema_web_page_type' => 'WebPage',
        'schema_web_page_name' => 'Apoie a Associação',
        'schema_web_page_description' => $description,
        'schema_web_page_url' => $supportUrl,
      ]);
    }
    elseif (str_starts_with((string) $route, 'aculta_portal.') && !in_array($route, self::PUBLIC_PORTAL_ROUTES, TRUE)) {
      // Rotas privadas da conta, painel e busca: noindex. As entradas públicas
      // de cada purpose estão em PUBLIC_PORTAL_ROUTES e são indexáveis.
      $tags['robots'] = 'noindex, nofollow';
    }
    elseif (in_array($route, self::AUTH_ROUTES, TRUE)) {
      // Login, cadastro, saída e recuperação de senha do Core: fluxos de conta,
      // sem conteúdo para indexação.
      $tags['robots'] = 'noindex, nofollow';
    }
    elseif (DomainRoutePolicy::isTransactionalSeoRouteName((string) $route)) {
      $tags['robots'] = 'noindex, nofollow';
      unset($tags['canonical_url'], $tags['og_url'], $tags['schema_web_page_url']);
      if (DomainRoutePolicy::isDonationFlowRouteName((string) $route)) {
        $tags['title'] = 'Apoio | Associação Cultural Antiproibicionista';
      }
    }
  }

}

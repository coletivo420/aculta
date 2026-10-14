<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Template\Attribute;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_portal\AccountShellBuilder;
use Drupal\aculta_portal\Auth\AuthIntegrationManager;
use Drupal\aculta_portal\Domain\AcultaBreadcrumbBuilder;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Domain\DomainRoutePolicy;
use Drupal\aculta_portal\Form\LoginPopupForm;
use Drupal\aculta_portal\Presentation\DomainPresentationBuilder;
use Drupal\aculta_portal\Presentation\SocialLinksPagePresenter;
use Drupal\block_content\BlockContentInterface;
use Drupal\aculta_portal\Account\StreetNumberField;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Drupal hooks for the ACULTA account portal.
 */
final class PortalHooks {

  /**
   * Entradas públicas de purpose, indexáveis e fora do noindex padrão do Portal.
   * Rotas `aculta_portal.*` não listadas aqui recebem noindex.
   */
  /** Rotas de autenticação e conta do Core (user.*), sempre com noindex. */
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
    private readonly \Drupal\aculta_portal\Search\SearchUtility $searchUtility,
    #[Autowire(service: 'aculta_portal.cart_utility')]
    private readonly \Drupal\aculta_portal\Cart\CartUtility $cartUtility,
    #[Autowire(service: 'extension.list.module')]
    private readonly ModuleExtensionList $moduleList,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
    #[Autowire(service: 'aculta_portal.auth_integration')]
    private readonly AuthIntegrationManager $authIntegrationManager,
    #[Autowire(service: 'aculta_portal.account_shell_builder')]
    private readonly AccountShellBuilder $accountShellBuilder,
    #[Autowire(service: 'aculta_portal.email_confirmation_notice')]
    private readonly \Drupal\aculta_portal\Account\EmailConfirmationNotice $emailConfirmationNotice,
    #[Autowire(service: 'aculta_portal.breadcrumb_builder')]
    private readonly AcultaBreadcrumbBuilder $breadcrumbBuilder,
    #[Autowire(service: 'aculta_portal.presentation.domain')]
    private readonly DomainPresentationBuilder $domainPresentationBuilder,
    #[Autowire(service: 'renderer')]
    private readonly RendererInterface $renderer,
    #[Autowire(service: 'aculta_portal.presentation.social_links_page')]
    private readonly SocialLinksPagePresenter $socialLinksPagePresenter,
  ) {}


  /**
   * Implements hook_preprocess_page().
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $domainPresentation = $this->domainPresentationBuilder->buildCurrent();
    $variables['domain_presentation'] = $domainPresentation?->toThemeArray();
    if (($variables['domain_presentation']['identity']['purpose'] ?? NULL) === 'links') {
      $variables['social_links_page_footer'] = $this->socialLinksPagePresenter->buildFooter();
    }
    $variables['aculta_search_utility'] = $this->searchUtility->build();
    // Ícone da ACULTA na barra multidomínio: página inicial do domínio principal (purpose main), nunca fixa no código.
    $mainHome = $this->domainPurposeManager->pathUrl('main', '/');
    $variables['aculta_brand_home'] = $mainHome !== NULL ? $mainHome->toString() : '/';
    $cart = $this->cartUtility->build();
    if (isset($variables['page']) && is_array($variables['page'])) {
      $this->renderer->addCacheableDependency($variables['page'], $cart['cache']);
    }
    unset($cart['cache']);
    $variables['aculta_cart'] = $cart;
    if ($domainPresentation !== NULL
      && isset($variables['page'])
      && is_array($variables['page'])) {
      $this->renderer->addCacheableDependency($variables['page'], $domainPresentation);
    }
    $account = $this->currentUser;
    $path = $this->currentPath->getPath();
    $request = $this->requestStack->getCurrentRequest();
    if ($request !== NULL
      && $account->isAuthenticated()
      && !$account->hasPermission('administer users')
      && preg_match('#^/user/([1-9][0-9]*)/edit$#D', $path, $matches)) {
      $target_uid = (int) $matches[1];
      if ($target_uid === (int) $account->id()
        && !\Drupal\aculta_portal\EventSubscriber\AccountRouteSubscriber::isValidCorePasswordResetRequest($request, $target_uid, (int) $account->id())) {
        $variables['aculta_account_edit_blocked'] = TRUE;
        $variables['aculta_account_security_url'] = Url::fromRoute('aculta_portal.security')->toString();
      }
    }

    $this->accountShellBuilder->build($variables);

    if (($variables['page']['content']['#theme'] ?? NULL) === 'aculta_portal_shell'
      && isset($variables['page']['header'])
      && is_array($variables['page']['header'])) {
      $this->removeHeaderBlockByPlugin($variables['page']['header'], 'page_title_block');
    }

    // Aviso de e-mail não confirmado em "Minha conta" (não descartável): acima do conteúdo da página.
    if (($notice = $this->emailConfirmationNotice->build()) !== NULL && isset($variables['page']['content'])) {
      $variables['page']['content'] = [
        '#type' => 'container',
        'aculta_email_notice' => $notice,
        'body' => $variables['page']['content'],
      ];
    }
  }

  /**
   * Removes a header block owned by the Portal shell before theme preprocess.
   */
  private function removeHeaderBlockByPlugin(array &$header, string $pluginId): void {
    foreach (Element::children($header) as $key) {
      $block = $header[$key];
      $candidate = (string) ($block['#plugin_id'] ?? '');
      $lazyBuilder = $block['#lazy_builder'] ?? [];
      if ($candidate === ''
        && ($lazyBuilder[0] ?? '') === 'Drupal\\block\\BlockViewBuilder::lazyBuilder') {
        $blockId = $lazyBuilder[1][0] ?? '';
        if (is_string($blockId) && $blockId !== '') {
          $candidate = (string) ($this->configFactory->get('block.block.' . $blockId)->get('plugin') ?? '');
        }
      }
      if ($candidate === $pluginId) {
        unset($header[$key]);
      }
    }
  }

  /**
   * Supplies presentation-only data for the institutional content block.
   */
  /**
   * Implements hook_preprocess_aculta_portal_element().
   *
   * Os elementos de presentation (ex.: busca da Wiki) chegam ao tema como array, sem objetos. O atributo é montado aqui,
   * no último passo antes do template, que espera um Attribute.
   */
  #[Hook('preprocess_aculta_portal_element')]
  public function preprocessPortalElement(array &$variables): void {
    if (is_array($variables['element_attributes'] ?? NULL)) {
      $variables['element_attributes'] = new Attribute($variables['element_attributes']);
    }
  }

  #[Hook('preprocess_block')]
  public function preprocessBlock(array &$variables): void {
    $entity = $variables['elements']['content']['#block_content'] ?? NULL;
    if (!$entity instanceof BlockContentInterface
      || $entity->bundle() !== 'aculta_institution') {
      return;
    }

    $variables['institution_entity'] = $entity;
    $variables['view_mode'] = $variables['elements']['content']['#view_mode'] ?? 'full';

    $nid = (int) $this->configFactory
      ->get('aculta_portal.settings')
      ->get('institution_transparency_nid');
    $variables['institution_transparency_url'] = $nid > 0
      ? $this->domainPurposeManager->routeUrl('main', 'entity.node.canonical', ['node' => $nid])
      : NULL;
  }

  /**
   * Supplies the current-page label consumed by the public breadcrumb template.
   */
  #[Hook('preprocess_breadcrumb')]
  public function preprocessBreadcrumb(array &$variables): void {
    $variables['aculta_current_breadcrumb'] = $this->breadcrumbBuilder
      ->currentTitle($this->routeMatch);
  }

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(array &$links): void {
    // The portal owns the account landing page, not the generic user profile.
    if (($links['user.page']['menu_name'] ?? NULL) === 'account') {
      unset($links['user.page']);
    }
  }

  /** Sends identity, support and editorial links to their canonical domains. */
  #[Hook('preprocess_menu')]
  public function preprocessMenu(array &$variables): void {
    if (empty($variables['items']) || !is_array($variables['items'])) {
      return;
    }
    $resolver = $this->domainPurposeManager;
    $this->decorateLoginPopupItems($variables['items']);
    $this->rewriteDomainMenuItems($variables['items'], $resolver);
    if (isset($variables['#cache']) && is_array($variables['#cache'])) {
      $variables['#cache']['contexts'] = array_values(array_unique(array_merge(
        $variables['#cache']['contexts'] ?? [],
        ['domain', 'url.path', 'url.query_args', 'user.roles'],
      )));
    }
  }

  /** Sends editorial author and category links to MAGAZINE. */
  #[Hook('preprocess_links')]
  public function preprocessLinks(array &$variables): void {
    if (!isset($variables['links']) || !is_array($variables['links'])) {
      return;
    }
    $resolver = $this->domainPurposeManager;
    foreach ($variables['links'] as &$link) {
      $url = $link['url'] ?? NULL;
      if (!$url instanceof Url || !$url->isRouted()) {
        continue;
      }

      $routeName = $url->getRouteName();
      if (DomainRoutePolicy::isCentralTransactionRouteName($routeName)) {
        $target = $resolver->routeUrl(
          'main',
          $routeName,
          $url->getRouteParameters(),
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
      $term_id = $url->getRouteParameters()['taxonomy_term'] ?? NULL;
      $term = is_numeric($term_id) ? $this->entityTypeManager->getStorage('taxonomy_term')->load((int) $term_id) : NULL;
      if ($term && in_array($term->bundle(), ['editorial_author', 'editorial_category'], TRUE)) {
        $link['url'] = $resolver->routeUrl('magazine', 'entity.taxonomy_term.canonical', ['taxonomy_term' => $term->id()]) ?? $url;
      }
    }
    unset($link);
  }

  /** Rewrites only known cross-domain links, keeping their menu structure. */
  /**
   * "Entrar" para visitante anônimo abre o popup de login no próprio host (use-ajax modal, mesma origem). O link
   * /entrar continua valendo sem JavaScript: a rota do popup também mostra o formulário em página.
   */
  private function decorateLoginPopupItems(array &$items): void {
    if ($this->currentUser->isAuthenticated()) {
      return;
    }
    $destination = LoginPopupForm::safeDestination($this->requestStack->getCurrentRequest()?->getRequestUri());
    foreach ($items as &$item) {
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
  }

  private function rewriteDomainMenuItems(array &$items, \Drupal\aculta_portal\Domain\DomainPurposeManager $resolver): void {
    foreach ($items as &$item) {
      $url = $item['url'] ?? NULL;
      if ($url instanceof Url) {
        $route = $url->isRouted() ? $url->getRouteName() : NULL;
        $uri = $url->isRouted() ? NULL : $url->getUri();
        $target = NULL;
        if (is_string($route) && DomainRoutePolicy::isCentralTransactionRouteName($route)) {
          $target = $resolver->routeUrl('main', $route, $url->getRouteParameters());
          $options = $url->getOptions();
          if ($target instanceof Url && !empty($options['query']) && is_array($options['query'])) {
            $target->setOption('query', $options['query']);
          }
        }
        elseif (in_array($route, ['user.login', 'user.register', 'user.pass', 'user.logout'], TRUE)) {
          $target = $resolver->routeUrl('account', $route, $url->getRouteParameters());
          if ($route === 'user.login' && !$this->currentUser->isAuthenticated()
            && $this->routeMatch->getRouteName() !== 'user.login') {
            $purpose = $resolver->getCurrentPurpose();
            $request = $this->requestStack->getCurrentRequest();
            $path = $request?->getPathInfo() ?? $this->currentPath->getPath();
            $destination = $request?->getRequestUri() ?? $path;
            if ($purpose !== NULL && str_starts_with($path, '/') && !str_starts_with($path, '//')
              && !preg_match('#^/(?:entrar|oauth|sair|recuperar(?:-senha|-acesso)?)(?:/|$)#', $path)) {
              $target?->setOption('query', [
                'destination' => $destination,
                'aculta_destination_purpose' => $purpose,
              ]);
            }
          }
        }
        elseif ($route === 'aculta_portal.dashboard' || $route === 'user.page') {
          $target = $resolver->pathUrl('account', '/');
        }
        elseif ($route === 'entity.node.canonical') {
          $parameters = $url->getRouteParameters();
          $node_parameter = $parameters['node'] ?? NULL;
          $node = $node_parameter instanceof \Drupal\node\NodeInterface
            ? $node_parameter
            : (is_numeric($node_parameter) ? $this->entityTypeManager->getStorage('node')->load((int) $node_parameter) : NULL);
          if ($node && $node->hasField('field_domain_source') && !$node->get('field_domain_source')->isEmpty()) {
            $purpose = $resolver->getPurposeForDomainId((string) $node->get('field_domain_source')->target_id);
            if ($purpose !== NULL) {
              $target = $resolver->routeUrl($purpose, 'entity.node.canonical', ['node' => $node->id()]);
            }
          }
        }
        elseif ($route === 'aculta_portal.support_form' || in_array($uri, ['internal:/apoio', 'internal:/apoie'], TRUE)) {
          $target = $resolver->pathUrl('support', '/');
        }
        elseif ($uri === 'internal:/noticias') {
          $target = $resolver->pathUrl('magazine', '/noticias');
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
   * Detalhe do pedido (Minha conta): a situação sai pelo nome do Portal, traduzido ("Concluído"), e não pelo rótulo
   * em inglês do workflow do Commerce. O título do campo vira "Situação".
   */
  #[Hook('entity_view_alter')]
  public function commerceOrderUserView(array &$build, $entity, $display): void {
    if ($entity->getEntityTypeId() !== 'commerce_order' || $display->id() !== 'commerce_order.default.user' || !isset($build['state'])) {
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
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $route = $this->routeMatch->getRouteName();
    // O "Entrar" da barra abre o popup por use-ajax modal (visitante anônimo, em qualquer host).
    if (!$this->currentUser->isAuthenticated()) {
      $attachments['#attached']['library'][] = 'core/drupal.dialog.ajax';
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
      $attachments['#attached']['library'][] = 'aculta_portal/account-navigation';
    }
  }

  /**
   * Keep the two Profile subsections focused while using the Profile entity form.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if ($form_id === 'user_login_form') {
      $form['name']['#title'] = $this->translation->translate('Nome de usuário ou e-mail');
      $form['name']['#description'] = $this->translation->translate('Informe seu e-mail ou nome de usuário.');
      $form['password_reset_link'] = [
        ...PortalLinkElement::build($this->translation->translate('Esqueci minha senha'), Url::fromRoute('user.pass'), ['data-portal-ui' => 'login-reset-link']),
        '#weight' => 10,
      ];
      $form['registration_notice'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'login-registration'],
        'text' => ['#plain_text' => $this->translation->translate('Ainda não tem uma conta?')],
        'link' => [
          '#type' => 'link',
          '#title' => $this->translation->translate('Ver informações sobre o cadastro'),
          '#url' => Url::fromRoute('user.register'),
          '#attributes' => ['data-portal-ui' => 'aculta-button-secondary'],
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
        'line_before' => ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#element_attributes' => new Attribute(['aria-hidden' => 'true'])],
        'label' => ['#plain_text' => $this->translation->translate('ou')],
        'line_after' => ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#element_attributes' => new Attribute(['aria-hidden' => 'true'])],
        '#weight' => 20,
      ];
      $form['social_auth'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'auth-provider'],
        'provider' => $this->blockManager
          ->createInstance('social_auth_login', [])
          ->build(),
        '#weight' => 21,
      ];
    }

    $form_object = $form_state->getFormObject();
    if (!method_exists($form_object, 'getEntity')) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity) {
      return;
    }

    $path = $this->currentPath->getPath();
    if ($entity->getEntityTypeId() === 'profile' && $entity->bundle() === 'participante') {
      if ($path === '/dados') {
        // Keep this tab limited to personal basics, even if older active
        // Profile display configuration still contains address components.
        $allowed = [
          'field_nickname',
          'field_first_name',
          'field_last_name',
          'field_whatsapp',
          'actions',
          'form_build_id',
          'form_token',
          'form_id',
        ];
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
      if ($path === '/dados' && isset($form['actions']['submit'])) {
        $form['actions']['submit']['#submit'][] = 'aculta_portal.form_callbacks:syncCustomerAddressNames';
      }
      return;
    }

    if ($entity->getEntityTypeId() === 'profile' && $entity->bundle() === 'customer'
      && $path === '/dados/endereco'
      && (int) $entity->getOwnerId() === (int) $this->currentUser->id()) {
      // The Portal and Commerce edit the same customer Profile Address.
      $allowed = ['address', 'actions', 'form_build_id', 'form_token', 'form_id'];
      foreach (array_keys($form) as $key) {
        if (!str_starts_with((string) $key, '#') && !in_array($key, $allowed, TRUE)) {
          unset($form[$key]);
        }
      }
      if (isset($form['address']['widget'][0]['address'])) {
        $address_element = &$form['address']['widget'][0]['address'];
        $address_element['#default_value']['country_code'] ??= 'BR';
        $participant = $this->entityTypeManager->getStorage('profile')->loadByUser($this->currentUser, 'participante');
        if ($participant) {
          $address_element['#default_value']['given_name'] ??= $participant->get('field_first_name')->value ?? '';
          $address_element['#default_value']['family_name'] ??= $participant->get('field_last_name')->value ?? '';
        }
        foreach ([
          'country_code' => 'País',
          'address_line1' => 'Endereço / Logradouro',
          'address_line2' => 'Complemento',
          'locality' => 'Cidade',
          'dependent_locality' => 'Bairro',
          'administrative_area' => 'Estado',
          'postal_code' => 'CEP',
          'sorting_code' => 'Código postal',
        ] as $key => $label) {
          if (isset($address_element[$key])) {
            $address_element[$key]['#title'] = $this->translation->translate($label);
          }
        }
        foreach (['given_name', 'additional_name', 'family_name'] as $private_or_duplicate) {
          if (isset($address_element[$private_or_duplicate])) {
            $address_element[$private_or_duplicate]['#access'] = FALSE;
          }
        }
        unset($address_element);
      }
      // Número e S/N: subcampos do endereço, logo depois da rua (StreetNumberField). Valores do perfil salvo.
      $parts = ['street' => '', 'number' => '', 'sn' => FALSE];
      if ($entity->id()) {
        $saved = $this->entityTypeManager->getStorage('profile')->load($entity->id());
        if ($saved && $saved->hasField('address') && !$saved->get('address')->isEmpty()) {
          $parts = \Drupal\aculta_portal\Account\StreetNumberField::split((string) $saved->get('address')->first()->getAddressLine1());
        }
      }
      if (isset($form['address']['widget'][0]['address'])) {
        \Drupal\aculta_portal\Account\StreetNumberField::attach($form['address']['widget'][0]['address'], $parts['number'], $parts['sn']);
      }
      // Junta rua e número antes do save (PortalFormCallbacks::buildStreetNumber).
      $form['#entity_builders'][] = 'aculta_portal.form_callbacks:buildStreetNumber';
      if (isset($form['actions']['submit'])) {
        $form['actions']['submit']['#value'] = $this->translation->translate('Salvar endereço');
        $form['actions']['submit']['#submit'][] = 'aculta_portal.form_callbacks:addressRedirect';
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
      if (isset($form['actions']['submit'])) {
        $form['actions']['submit']['#value'] = $this->translation->translate('Salvar foto do perfil');
        $form['actions']['submit']['#submit'][] = 'aculta_portal.form_callbacks:accountPhotoRedirect';
      }
    }
  }

  /**
   * Implements hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    foreach ($attachments['#attached']['html_head'] ?? [] as $delta => $item) {
      $element = $item[0] ?? [];
      if (($element['#tag'] ?? NULL) === 'meta' && strtolower($element['#attributes']['name'] ?? '') === 'generator') {
        unset($attachments['#attached']['html_head'][$delta]);
      }
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'aculta_portal_account_terms_modal' => [
        'variables' => [
          'title' => NULL,
          'form' => NULL,
        ],
        'template' => 'aculta-portal-account-terms-modal',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
      'aculta_portal_payment_notice' => [
        'variables' => [
          'intro' => NULL,
          'link_label' => NULL,
          'url' => NULL,
          'logo' => NULL,
        ],
        'template' => 'portal-payment-notice',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
      'aculta_portal_element' => [
        'variables' => [
          'tag' => 'span',
          'value' => NULL,
          'element_attributes' => NULL,
        ],
        'template' => 'portal-element',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
      'aculta_portal_search_trigger' => [
        'variables' => [
          'url' => NULL,
          'label' => NULL,
          'trigger_attributes' => NULL,
        ],
        'template' => 'portal-search-trigger',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
      'aculta_portal_cart_status' => [
        'variables' => [
          'status_type' => 'success',
          'status_role' => 'status',
          'message' => NULL,
          'url' => NULL,
          'link_label' => NULL,
          'close_label' => NULL,
        ],
        'template' => 'portal-cart-status',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
      'aculta_portal_shell' => [
        'variables' => [
          'section_title' => NULL,
          'menu_items' => [],
          'accordion_items' => [],
          'logout' => [],
          'content' => NULL,
        ],
        'template' => 'aculta-portal-shell',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
      'aculta_section' => [
        'variables' => [
          'section_title' => NULL,
          'section_heading' => NULL,
          'section_variant' => 'default',
          'section_body' => NULL,
        ],
        'template' => 'aculta-section',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
      'aculta_portal_photo_editor' => [
        'variables' => [
          'photo' => NULL,
          'photo_form' => NULL,
          'display_name' => NULL,
        ],
        'template' => 'aculta-portal-photo-editor',
        'path' => $this->moduleList->getPath('aculta_portal') . '/templates',
      ],
    ];
  }

  /**
   * Supplies metadata for the public support page and private result routes.
   */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$tags, array $context): void {
    $route = $this->routeMatch->getRouteName();
    if ($route === 'aculta_portal.support_form') {
      $description = 'Apoie os projetos culturais, ações de formação, comunicação, cuidado e participação social da Associação Cultural Antiproibicionista.';
      $supportUrl = $this->domainPurposeManager->routeUrl('support', '<front>');
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

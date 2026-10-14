<?php

declare(strict_types=1);

namespace Drupal\aculta420\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Render\Element;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Presentation-only hooks for ACULTA420.
 */
final class ThemeHooks {

  public function __construct(
    private readonly PathMatcherInterface $pathMatcher,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RouteMatchInterface $routeMatch,
    #[Autowire(service: 'extension.list.theme')]
    private readonly ExtensionList $themes,
  ) {}

  /**
   * Keeps a solitary editorial highlight static using native VVJB options.
   */
  #[Hook('preprocess_views_view_vvjb')]
  public function preprocessViewsViewVvjb(array &$variables): void {
    if (($variables['view']?->id() ?? '') !== 'home_editorial_highlights') {
      return;
    }
    if (count($variables['rows'] ?? []) === 1) {
      $variables['options']['slide_time'] = 0;
      $variables['options']['navigation'] = 'none';
      $variables['options']['show_play_pause'] = FALSE;
      $variables['options']['show_progress_bar'] = FALSE;
      $variables['options']['show_page_counter'] = FALSE;
    }
  }

  /**
   * Feeds the home hero pattern from the page fields on the full view.
   *
   * Only pages that filled the hero title get it. The hero is a render array
   * with weight -100, so its access and cache metadata bubble with the node.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['elements']['#node'] ?? NULL;
    if (!$node instanceof NodeInterface
      || $node->bundle() !== 'page'
      || ($variables['view_mode'] ?? '') !== 'full'
      || !$node->hasField('field_hero_title')
      || $node->get('field_hero_title')->isEmpty()) {
      return;
    }

    $actions = [];
    $classes = [
      'field_hero_primary' => ['btn', 'btn-primary'],
      'field_hero_secondary' => ['btn', 'btn-outline-primary'],
    ];
    foreach ($classes as $field => $class) {
      $item = $node->get($field)->first();
      if ($item === NULL || $item->uri === NULL || $item->uri === '') {
        continue;
      }
      $actions[] = Link::fromTextAndUrl(
        $item->title !== NULL && $item->title !== '' ? $item->title : $item->uri,
        Url::fromUri($item->uri, ['attributes' => ['class' => $class]]),
      )->toRenderable();
    }

    $text = static fn(string $field): ?string => $node->hasField($field) && !$node->get($field)->isEmpty()
      ? (string) $node->get($field)->value
      : NULL;

    $variables['content']['aculta_hero'] = [
      '#type' => 'component',
      '#component' => 'aculta420:hero',
      '#props' => [
        'eyebrow' => $text('field_hero_eyebrow'),
        'title' => (string) $text('field_hero_title'),
        'slogan' => $text('field_hero_slogan'),
        'lead' => $text('field_hero_lead'),
      ],
      '#slots' => ['actions' => $actions],
      '#weight' => -100,
    ];
  }

  /**
   * Adds a 2x source to the project card image (1x style: 256 px).
   *
   * The 1x file is the img src; retina screens take the 512 px variant. Only the
   * card style is touched, so other images keep their single source.
   */
  #[Hook('preprocess_image_style')]
  public function preprocessImageStyle(array &$variables): void {
    if (($variables['style_name'] ?? '') !== 'aculta_card_1x' || empty($variables['uri'])) {
      return;
    }
    $styles = $this->entityTypeManager->getStorage('image_style');
    $one_x = $styles->load('aculta_card_1x');
    $two_x = $styles->load('aculta_card_2x');
    if ($one_x === NULL || $two_x === NULL) {
      return;
    }
    if (!isset($variables['image']['#attributes'])) {
      return;
    }
    $variables['image']['#attributes']['srcset'] = $one_x->buildUrl($variables['uri']) . ' 1x, '
      . $two_x->buildUrl($variables['uri']) . ' 2x';
  }

  /**
   * Marks editorial body fields as prose without styling unrelated links.
   */
  #[Hook('preprocess_field')]
  public function preprocessField(array &$variables): void {
    if (($variables['entity_type'] ?? '') === 'private_message_thread'
      && ($variables['field_name'] ?? '') === 'private_message_form') {
      if (!is_array($variables['items'] ?? NULL)) {
        return;
      }
      foreach ($variables['items'] as &$item) {
        if (!is_array($item['content'] ?? NULL)) {
          continue;
        }
        $form = $item['content'];
        $item['content'] = [
          '#type' => 'component',
          '#component' => 'aculta420:messenger-compose',
          '#slots' => ['form' => $form],
        ];
      }
      unset($item);
      return;
    }

    if (($variables['entity_type'] ?? '') !== 'node'
      || ($variables['field_name'] ?? '') !== 'body') {
      return;
    }

    $entity = $variables['element']['#object'] ?? NULL;
    if (!$entity instanceof NodeInterface
      || !in_array($entity->bundle(), [
        'page',
        'article',
        'activity',
        'project',
        'editorial_highlight',
        'document',
      ], TRUE)) {
      return;
    }

    if (isset($variables['attributes']) && is_object($variables['attributes'])
      && method_exists($variables['attributes'], 'addClass')) {
      $variables['attributes']->addClass('aculta-prose');
    }
    elseif (isset($variables['attributes']) && is_array($variables['attributes'])) {
      $variables['attributes']['class'][] = 'aculta-prose';
    }
  }

  /**
   * Marca do domínio: o Portal envia uma chave neutra ('#brand_key'); o asset é do tema.
   *
   * Sem chave conhecida, o dado já é render array e segue sem alteração.
   */
  private function brandMediaRender(array $brandMedia): array {
    if (($brandMedia['#brand_key'] ?? NULL) !== 'wiki420') {
      return $brandMedia;
    }
    $dir = $this->themes->getPath('aculta420') . '/assets/branding/wiki420/web/';
    if (!is_file(DRUPAL_ROOT . '/' . $dir . 'wiki420-horizontal-960w.webp')) {
      return [];
    }
    // O cabeçalho mostra a logo com no máximo 18rem; o navegador escolhe a densidade.
    $srcset = [];
    foreach (['480w' => 480, '720w' => 720, '960w' => 960] as $suffix => $width) {
      $srcset[] = Url::fromUri('base:' . $dir . 'wiki420-horizontal-' . $suffix . '.webp')->toString() . ' ' . $width . 'w';
    }
    return [
      '#theme' => 'image',
      '#uri' => Url::fromUri('base:' . $dir . 'wiki420-horizontal-960w.webp')->toString(),
      '#alt' => $brandMedia['#alt'] ?? '',
      '#width' => 960,
      '#height' => 307,
      '#attributes' => [
        'data-portal-ui' => 'domain-brand-image',
        'srcset' => implode(', ', $srcset),
        'sizes' => '18rem',
        // Logo acima da dobra e principal visual do cabeçalho: sem carregamento preguiçoso.
        'loading' => 'eager',
        'fetchpriority' => 'high',
      ],
    ];
  }

  /**
   * Composes stored header blocks into the current public shell.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $routeName = $this->routeMatch->getRouteName();
    $this->presentMessengerTrigger($variables);
    $this->presentAlertsTrigger($variables);
    $this->presentMessengerPage($variables, $routeName);
    $routeNode = $this->routeMatch->getParameter('node');
    $wikiEntryPage = $routeName === 'entity.node.canonical'
      && $routeNode instanceof NodeInterface
      && $routeNode->bundle() === 'wiki_entry';
    $variables['institutional_home'] = $this->pathMatcher->isFrontPage();
    $brandMedia = $variables['domain_presentation']['regions']['brand_media'] ?? NULL;
    $variables['aculta_domain_brand_media'] = is_array($brandMedia) ? $this->brandMediaRender($brandMedia) : [];
    // The text fallback is only built when there is no brand media; otherwise the
    // header would show two home links (image and text) for the same destination.
    $variables['aculta_domain_brand_fallback'] = $variables['aculta_domain_brand_media'] === []
      ? $this->buildDomainBrandFallback($variables['domain_presentation']['identity'] ?? NULL)
      : NULL;

    $destinations = [
      'page_title_block' => 'aculta_page_title',
      'system_breadcrumb_block' => 'aculta_breadcrumb',
      'system_menu_block:account' => 'aculta_account_menu',
      'help_block' => 'aculta_help',
    ];
    foreach ($destinations as $destination) {
      $variables[$destination] = [];
    }
    $variables['aculta_has_system_branding'] = FALSE;

    if (!isset($variables['page']['header'])) {
      return;
    }

    foreach (Element::children($variables['page']['header']) as $key) {
      $block = $variables['page']['header'][$key];
      $pluginId = $block['#plugin_id'] ?? '';
      $lazyBuilder = $block['#lazy_builder'] ?? [];
      if ($pluginId === ''
        && ($lazyBuilder[0] ?? '') === 'Drupal\\block\\BlockViewBuilder::lazyBuilder') {
        $blockId = $lazyBuilder[1][0] ?? '';
        $pluginId = is_string($blockId) && $blockId !== ''
          ? (string) ($this->configFactory->get('block.block.' . $blockId)->get('plugin') ?? '')
          : '';
      }
      if ($pluginId === 'system_branding_block') {
        if ($variables['aculta_domain_brand_media'] !== []) {
          unset($variables['page']['header'][$key]);
          continue;
        }
        $variables['aculta_has_system_branding'] = TRUE;
      }

      $destination = $destinations[$pluginId] ?? NULL;
      if ($destination !== NULL) {
        // The Wiki entry presenter owns the document H1; avoid a duplicate page title.
        if ($wikiEntryPage && $destination === 'aculta_page_title') {
          unset($variables['page']['header'][$key]);
          continue;
        }
        $variables[$destination][$key] = $block;
        unset($variables['page']['header'][$key]);
      }
    }
  }

  /**
   * Maps Messenger's pages to visual SDCs while keeping access/render logic in the module.
   */
  private function presentMessengerPage(array &$variables, ?string $routeName): void {
    $component = match ($routeName) {
      'aculta_messenger.messages', 'aculta_messenger.notifications', 'aculta_messenger.notices', 'private_message.private_message_page' => 'aculta420:messenger-list',
      'aculta_messenger.preview' => 'aculta420:messenger-list',
      'private_message.private_message_create' => 'aculta420:messenger-compose',
      default => NULL,
    };
    if ($component === NULL || !isset($variables['page']['content']) || !is_array($variables['page']['content'])) {
      return;
    }

    $content =& $variables['page']['content'];
    if (isset($content['#content']) && is_array($content['#content'])) {
      $content =& $content['#content'];
    }

    $original = $content;
    $props = [];
    $slot = 'content';
    if ($routeName === 'aculta_messenger.messages') {
      $props['label'] = 'Ações de mensagens';
    }
    elseif ($routeName === 'aculta_messenger.notifications') {
      $props['label'] = 'Notificações';
    }
    elseif ($routeName === 'aculta_messenger.notices') {
      $props['label'] = 'Avisos';
    }
    elseif ($routeName === 'private_message.private_message_page') {
      $props['label'] = 'Conversas';
    }
    elseif ($routeName === 'aculta_messenger.preview') {
      $props['label'] = 'Conversas recentes';
    }
    elseif ($routeName === 'private_message.private_message_create') {
      $slot = 'form';
    }

    $content = [
      '#type' => 'component',
      '#component' => $component,
      '#props' => $props,
      '#slots' => [$slot => $original],
    ];
  }

  /** Maps the Portal's neutral Messenger action to its theme SDC. */
  private function presentMessengerTrigger(array &$variables): void {
    $messenger = $variables['aculta_messenger'] ?? NULL;
    if (!is_array($messenger) || !is_string($messenger['url'] ?? NULL)) {
      $variables['aculta_messenger_trigger'] = [];
      return;
    }

    $variables['aculta_messenger_trigger'] = [
      '#type' => 'component',
      '#component' => 'aculta420:messenger-trigger',
      '#props' => [
        'href' => $messenger['url'],
        'count' => max(0, (int) ($messenger['unread_count'] ?? 0)),
      ],
    ];
    unset($variables['aculta_messenger']);
  }

  /** Maps the Portal's neutral alert action to the theme's SDC. */
  private function presentAlertsTrigger(array &$variables): void {
    $alerts = $variables['aculta_alerts'] ?? NULL;
    if (!is_array($alerts) || !is_string($alerts['url'] ?? NULL)) {
      $variables['aculta_alerts_trigger'] = [];
      return;
    }
    $variables['aculta_alerts_trigger'] = [
      '#type' => 'component',
      '#component' => 'aculta420:alerts-trigger',
      '#props' => [
        'href' => $alerts['url'],
        'count' => max(0, (int) ($alerts['count'] ?? 0)),
        'count_kind' => $alerts['count_kind'] ?? 'active',
      ],
    ];
    unset($variables['aculta_alerts']);
  }

  /** Exposes Private Message's native per-thread read state to accessible UI. */
  #[Hook('preprocess_private_message_thread')]
  public function preprocessPrivateMessageThread(array &$variables): void {
    $status = $variables['aculta_messenger_read_status'] ?? NULL;
    if (!in_array($status, ['read', 'unread'], TRUE)) {
      return;
    }
    $variables['read_status'] = [
      '#type' => 'component',
      '#component' => 'aculta420:messenger-read-status',
      '#props' => ['unread' => $status === 'unread'],
    ];
  }

  /** Wraps the native Private Message entity output in the theme's message item SDC. */
  #[Hook('preprocess_private_message')]
  public function preprocessPrivateMessage(array &$variables): void {
    if (!is_array($variables['content'] ?? NULL)) {
      return;
    }
    $content = $variables['content'];
    $variables['content'] = [
      '#type' => 'component',
      '#component' => 'aculta420:messenger-item',
      '#slots' => ['content' => $content],
    ];
  }

  /** Wraps the account-owned Message output without changing its data or access. */
  #[Hook('preprocess_message')]
  public function preprocessMessage(array &$variables): void {
    if ($this->routeMatch->getRouteName() !== 'aculta_messenger.notifications'
      || !is_array($variables['content'] ?? NULL)) {
      return;
    }
    $content = $variables['content'];
    $variables['content'] = [
      '#type' => 'component',
      '#component' => 'aculta420:messenger-notification',
      '#slots' => ['content' => $content],
    ];
  }

  /**
   * Builds the minimal textual branding fallback from the neutral identity.
   *
   * @param mixed $identity
   *   The neutral identity array prepared by the Portal presentation layer.
   *
   * @return array{label: string, home_url: string}|null
   *   Presentation-only fallback data, or NULL when the contract is absent
   *   or incomplete. No Domain lookup or functional fallback happens here.
   */
  private function buildDomainBrandFallback(mixed $identity): ?array {
    if (!is_array($identity)) {
      return NULL;
    }

    $purpose = isset($identity['purpose']) && is_string($identity['purpose'])
      ? trim($identity['purpose'])
      : '';
    $title = isset($identity['title']) && is_string($identity['title'])
      ? trim($identity['title'])
      : '';
    $homeUrl = isset($identity['home_url']) && is_string($identity['home_url'])
      ? trim($identity['home_url'])
      : '';

    if ($purpose === '' || $title === '' || $homeUrl === '') {
      return NULL;
    }

    $shortTitle = isset($identity['short_title']) && is_string($identity['short_title'])
      ? trim($identity['short_title'])
      : '';

    return [
      'label' => $shortTitle !== '' ? $shortTitle : $title,
      'home_url' => $homeUrl,
    ];
  }

  /**
   * Aplica o modo de cor que o Portal resolveu (contrato aculta_color_mode: light, dark ou auto).
   *
   * O html.html.twig do Bootstrap 5 grava data-bs-theme a partir de b5_theme_mode, e sobrescreve
   * qualquer atributo colocado em html_attributes. Por isso o modo entra por essa variável. Em auto
   * o valor fica vazio: o atributo não é "light", e o bloco prefers-color-scheme de tokens.css decide.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $mode = $variables['aculta_color_mode'] ?? 'light';
    $variables['b5_theme_mode'] = match ($mode) {
      'dark' => 'dark',
      'auto' => '',
      default => 'light',
    };
    // Título do documento informado pelo Portal (contrato aculta_head_title). Roda depois do Metatag.
    if (!empty($variables['aculta_head_title'])) {
      $variables['head_title'] = ['title' => (string) $variables['aculta_head_title']];
    }
  }

}

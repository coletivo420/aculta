<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Presentation;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\views\ViewExecutableFactory;

/** Builds the social channels and project carousel for the LINKS purpose. */
final class SocialLinksPagePresenter {

  private const CHANNELS = [
    'instagram' => ['label' => 'Instagram', 'icon' => 'instagram', 'hosts' => ['instagram.com', 'www.instagram.com']],
    'youtube' => ['label' => 'YouTube', 'icon' => 'youtube', 'hosts' => ['youtube.com', 'www.youtube.com', 'youtu.be']],
    'facebook' => ['label' => 'Facebook', 'icon' => 'facebook', 'hosts' => ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.com', 'www.fb.com']],
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ViewExecutableFactory $viewExecutableFactory,
    private readonly TranslationInterface $translation,
    private readonly DomainPurposeManager $domainPurpose,
  ) {}

  /** Builds the fixed MAIN CTA and administrator-configured additional buttons. */
  public function buildSiteButtons(): array {
    $container = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'social-links-button-list'],
      '#cache' => ['tags' => ['config:aculta_portal.social_links']],
    ];
    $has_buttons = FALSE;
    $url = $this->domainPurpose->pathUrl('main', '/');
    if ($url !== NULL) {
      $main_link = [
        '#type' => 'link',
        '#title' => $this->translation->translate('Visite nosso Site'),
        '#url' => $url,
        '#attributes' => ['data-portal-ui' => 'social-links-channel social-links-channel-website'],
      ];
      $metadata = (new CacheableMetadata())
        ->addCacheContexts(['url.site', 'languages:language_interface']);
      $mainDomain = $this->domainPurpose->getDomain('main');
      if ($mainDomain !== NULL) {
        $metadata->addCacheableDependency($mainDomain);
      }
      $metadata->applyTo($main_link);
      $container['main'] = $main_link;
    }

    foreach (($this->configFactory->get('aculta_portal.social_links')->get('buttons') ?? []) as $index => $button) {
      $text = trim((string) ($button['text'] ?? ''));
      $uri = trim((string) ($button['url'] ?? ''));
      $buttonUrl = $this->buttonUrl($uri);
      if ($text === '' || $buttonUrl === NULL) {
        continue;
      }
      $container['button_' . $index] = [
        '#type' => 'link',
        '#title' => $text,
        '#url' => $buttonUrl,
        '#attributes' => ['data-portal-ui' => 'social-links-channel social-links-channel-custom'],
      ];
      $has_buttons = TRUE;
    }
    return isset($container['main']) || $has_buttons ? $container : [];
  }

  /** Resolves only HTTPS URLs or safe root-relative paths from configuration. */
  private function buttonUrl(string $uri): ?Url {
    if ($uri === '' || preg_match('/[\x00-\x20\\\\]/', $uri)) {
      return NULL;
    }
    if (str_starts_with($uri, '/') && !str_starts_with($uri, '//')) {
      return Url::fromUserInput($uri);
    }
    $parts = parse_url($uri);
    if (filter_var($uri, FILTER_VALIDATE_URL) === FALSE
      || !is_array($parts)
      || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
      || !isset($parts['host'])
      || isset($parts['user'])
      || isset($parts['pass'])
      || isset($parts['port'])) {
      return NULL;
    }
    return Url::fromUri($uri);
  }

  /** Builds configured, official social channel links as a render array. */
  public function buildChannels(): array {
    $config = $this->configFactory->get('aculta_portal.social_links');
    $items = [];
    foreach (self::CHANNELS as $id => $channel) {
      $uri = trim((string) $config->get($id));
      if (!$this->isAllowedUrl($uri, $channel['hosts'])) {
        continue;
      }
      $items[] = [
        '#type' => 'link',
        '#title' => [
          'icon' => [
            '#type' => 'html_tag',
            '#tag' => 'i',
            '#attributes' => ['data-portal-ui' => 'social-links-icon-' . $id, 'aria-hidden' => 'true'],
            '#value' => '',
          ],
          'label' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => $channel['label']],
        ],
        '#url' => Url::fromUri($uri),
        '#attributes' => ['data-portal-ui' => 'social-links-channel social-links-channel-' . $id, 'rel' => 'noopener noreferrer', 'target' => '_blank'],
      ];
    }

    $list = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $items,
      '#attributes' => ['data-portal-ui' => 'social-links-list'],
      '#cache' => ['tags' => ['config:aculta_portal.social_links']],
    ];
    return $list;
  }

  /** Renders the existing ACULTA project teaser View through the VVJB display. */
  public function buildProjects(): array {
    $view = $this->entityTypeManager->getStorage('view')->load('aculta_projects');
    if ($view === NULL) {
      return [];
    }

    $executable = $this->viewExecutableFactory->get($view);
    return $executable->render('social_links') ?? [];
  }

  /** Builds the dedicated page footer, including the configured social links. */
  public function buildFooter(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'social-links-footer-inner'],
      'identity' => [
        '#plain_text' => $this->translation->translate('ACULTA - Associação Cultural Antiproibicionista'),
      ],
      'channels' => $this->buildChannels(),
      '#cache' => ['tags' => ['config:aculta_portal.social_links']],
    ];
  }

  /** Ensures imported config cannot render arbitrary protocols or hosts. */
  private function isAllowedUrl(string $uri, array $hosts): bool {
    $parts = parse_url($uri);
    return filter_var($uri, FILTER_VALIDATE_URL) !== FALSE
      && is_array($parts)
      && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
      && in_array(strtolower((string) ($parts['host'] ?? '')), $hosts, TRUE)
      && !isset($parts['user'], $parts['pass']);
  }

}

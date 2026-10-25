<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_links\Service\SocialLinksPagePresenter;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides the neutral node template suggestion for the social links page. */
final class SocialLinksThemeSuggestions {

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurpose,
    #[Autowire(service: 'aculta_links.social_links_page')]
    private readonly SocialLinksPagePresenter $pagePresenter,
  ) {}

  /**
 * Adds the LINKS-specific identity/footer to the public page render array. */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    if ($this->domainPurpose->getCurrentPurpose() === 'links') {
      $variables['social_links_page_footer'] = $this->pagePresenter->buildFooter();
    }
  }

  /**
 * Selects the social-links presentation for its own public node and purpose. */
  #[Hook('theme_suggestions_node_alter')]
  public function themeSuggestionsNodeAlter(array &$suggestions, array $variables): void {
    $node = $variables['elements']['#node'] ?? NULL;
    if (!$node instanceof NodeInterface
      || $node->bundle() !== 'page'
      || ($variables['elements']['#view_mode'] ?? NULL) !== 'full'
      || !$node->hasField('field_domain_source')
      || $node->get('field_domain_source')->isEmpty()) {
      return;
    }

    $sourceDomainId = (string) $node->get('field_domain_source')->target_id;
    if ($this->domainPurpose->getPurposeForDomainId($sourceDomainId) === 'links') {
      $suggestions[] = 'node__social_links';
    }
  }

  /**
 * Supplies public project links to the neutral content template contract. */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface
      || $node->bundle() !== 'page'
      || ($variables['view_mode'] ?? NULL) !== 'full'
      || !$node->hasField('field_domain_source')
      || $node->get('field_domain_source')->isEmpty()) {
      return;
    }

    $sourceDomainId = (string) $node->get('field_domain_source')->target_id;
    if ($this->domainPurpose->getPurposeForDomainId($sourceDomainId) === 'links') {
      $variables['social_links_site_link'] = $this->pagePresenter->buildSiteButtons();
      $variables['social_links_has_items'] = !empty($variables['social_links_site_link']);
    }
  }

}

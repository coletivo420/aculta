<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Plugin\metatag\Tag\PostalAddressTag;
use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Editorial, metadata, and library hook implementations.
 */
final class EditorialHooks {

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
    #[Autowire(service: 'path.current')]
    private readonly CurrentPathStack $currentPath,
  ) {}

  /**
   * Hands editorial basic blocks to the section theme hook.
   *
   * The Portal prepares neutral section data (title, sub-heading, variant and
   * body render array). The theme decides the markup in its aculta-section
   * template.
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof BlockContentInterface || $entity->bundle() !== 'basic') {
      return;
    }
    $build['#theme'] = 'aculta_section';
    $build['#section_title'] = $entity->hasField('field_section_title') ? ($entity->get('field_section_title')->value ?: NULL) : NULL;
    $build['#section_heading'] = $entity->hasField('field_section_heading') ? ($entity->get('field_section_heading')->value ?: NULL) : NULL;
    $build['#section_variant'] = $entity->hasField('field_section_variant') ? ($entity->get('field_section_variant')->value ?: 'default') : 'default';
    $build['#section_body'] = $build['body'] ?? NULL;
    unset($build['body']);
  }

  /** Preserves punctuation in structured postal addresses. */
  #[Hook('metatag_tags_alter')]
  public function metatagTagsAlter(array &$definitions): void {
    foreach (['schema_organization_address', 'schema_event_location'] as $id) {
      if (isset($definitions[$id])) {
        $definitions[$id]['class'] = PostalAddressTag::class;
      }
    }
  }

  /** Adds canonical and structured metadata owned by the Portal. */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$tags, array &$context): void {
    $entity = $context['entity'] ?? NULL;
    $node = $entity instanceof NodeInterface ? $entity : NULL;

    if ($entity instanceof GroupInterface && $entity->bundle() === 'lms_course') {
      $canonical = $this->domainPurposeManager->canonicalRouteUrl(
        'courses',
        'entity.group.canonical',
        ['group' => $entity->id()],
      );
      if ($canonical !== NULL) {
        $canonicalUrl = $canonical->toString();
        $tags['canonical_url'] = $canonicalUrl;
        $tags['og_url'] = $canonicalUrl;
      }
    }

    // Página que é a raiz do host (caminho interno de FRONT_PATHS, ex.: /loja-da-aculta): o canonical é a raiz do host.
    $purpose = $this->domainPurposeManager->getCurrentPurpose();
    $frontPath = \Drupal\aculta_portal\PathProcessor\PurposeFrontPathProcessor::FRONT_PATHS[$purpose] ?? NULL;
    $paginaRaiz = $frontPath !== NULL && ($node === NULL
      ? $this->currentPath->getPath() === $frontPath
      : $node->toUrl()->setAbsolute(FALSE)->toString() === $frontPath);
    if ($paginaRaiz) {
      $root = $this->domainPurposeManager->pathUrl($purpose, '/');
      if ($root !== NULL) {
        $tags['canonical_url'] = $root->toString();
        $tags['og_url'] = $root->toString();
      }
    }

    if ($node !== NULL && $node->bundle() === 'wiki_entry') {
      $canonical = $this->domainPurposeManager->canonicalRouteUrl(
        'wiki',
        'entity.node.canonical',
        ['node' => $node->id()],
      );
      if ($canonical !== NULL) {
        $canonicalUrl = $canonical->toString();
        $tags['canonical_url'] = $canonicalUrl;
        $tags['og_url'] = $canonicalUrl;
      }
    }

  }

}

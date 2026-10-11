<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Resolves route/content ownership without enforcing the HTTP response.
 *
 * DomainPurposeRequestSubscriber owns HTTP enforcement/canonicalization. This
 * service only translates Drupal route/content state into a stable ACULTA
 * purpose, with platform administration taking precedence over content purpose.
 */
final class ContentPurposeResolver {

  private const WIKI_NODE_ROUTES = [
    'entity.node.edit_form',
    'entity.node.version_history',
    'entity.node.revision',
    'node.revision_revert_confirm',
    'node.revision_revert_translation_confirm',
    'node.revision_delete_confirm',
    'diff.revisions_diff',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly DomainPurposeManager $domainPurpose,
  ) {}

  public function requiredPurpose(
    Route $route,
    string $routeName,
    Request $request,
    array $matched = [],
  ): ?string {
    // Platform-level route metadata is authoritative and precedes content
    // ownership (Wiki, Courses, etc.).
    if ($route->getOption('_admin_route')
      || $route->getOption('_aculta_domain_purpose') === 'main') {
      return 'main';
    }

    $groupParameter = $matched['group'] ?? $request->attributes->get('group');
    $group = is_object($groupParameter) ? $groupParameter : NULL;
    if (!$group && is_numeric($groupParameter)) {
      $group = $this->entities->getStorage('group')->load((int) $groupParameter);
    }
    if ($group && method_exists($group, 'bundle') && $group->bundle() === 'lms_course') {
      return 'courses';
    }

    if ($routeName === 'node.add') {
      $nodeType = $matched['node_type'] ?? $request->attributes->get('node_type');
      if (is_object($nodeType) && method_exists($nodeType, 'id')) {
        $nodeType = $nodeType->id();
      }
      if ($nodeType === 'wiki_entry') {
        return 'wiki';
      }
    }

    if (in_array($routeName, self::WIKI_NODE_ROUTES, TRUE)) {
      $node = $request->attributes->get('node') ?? ($matched['node'] ?? NULL);
      if (is_numeric($node)) {
        $node = $this->entities->getStorage('node')->load((int) $node);
      }
      if (is_object($node) && method_exists($node, 'bundle') && $node->bundle() === 'wiki_entry') {
        return 'wiki';
      }
    }

    return $route->getOption('_aculta_domain_purpose');
  }

  /**
   * Returns the canonical purpose of a direct public content request.
   */
  public function canonicalContentPurpose(Request $request): ?string {
    $routeName = (string) $request->attributes->get('_route');
    $raw = $request->attributes->get('_raw_variables');

    if ($routeName === 'entity.node.canonical') {
      $parameter = $request->attributes->get('node');
      if ($parameter === NULL && $raw instanceof ParameterBag) {
        $parameter = $raw->get('node');
      }
      $node = is_numeric($parameter)
        ? $this->entities->getStorage('node')->load((int) $parameter)
        : (is_object($parameter) ? $parameter : NULL);

      // Tópicos do fórum (nós do tipo forum) pertencem ao purpose FORUM.
      if ($node && $node->bundle() === 'forum') {
        return 'forum';
      }

      if ($node && $node->hasField('field_domain_source') && !$node->get('field_domain_source')->isEmpty()) {
        return $this->domainPurpose->getPurposeForDomainId(
          (string) $node->get('field_domain_source')->target_id
        );
      }
    }

    if ($routeName === 'entity.taxonomy_term.canonical') {
      $parameter = $request->attributes->get('taxonomy_term');
      if ($parameter === NULL && $raw instanceof ParameterBag) {
        $parameter = $raw->get('taxonomy_term');
      }
      $term = is_numeric($parameter)
        ? $this->entities->getStorage('taxonomy_term')->load((int) $parameter)
        : (is_object($parameter) ? $parameter : NULL);

      if ($term && method_exists($term, 'bundle')) {
        return match ($term->bundle()) {
          'wiki_category' => 'wiki',
          'editorial_author', 'editorial_category' => 'magazine',
          default => NULL,
        };
      }
    }

    return NULL;
  }

}

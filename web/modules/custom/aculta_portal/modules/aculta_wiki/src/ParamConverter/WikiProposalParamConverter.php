<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\ParamConverter;

use Drupal\aculta_contributions\Entity\Contribution;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Symfony\Component\Routing\Route;

/**
 * Resolves Wiki proposal IDs across the legacy and shared entity stores.
 */
final class WikiProposalParamConverter implements ParamConverterInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults): WikiProposal|Contribution|null {
    if (!is_string($value) && !is_int($value)) {
      return NULL;
    }
    $id = (string) $value;
    if (!preg_match('/^[1-9][0-9]{0,12}$/D', $id)) {
      return NULL;
    }

    $contribution = $this->entityTypeManager
      ->getStorage('aculta_contribution')
      ->load((int) $id);
    if ($contribution instanceof Contribution && $contribution->get('source')->value === 'wiki') {
      return $contribution;
    }

    $proposal = $this->entityTypeManager
      ->getStorage('aculta_wiki_proposal')
      ->load((int) $id);
    return $proposal instanceof WikiProposal ? $proposal : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
    return ($definition['type'] ?? NULL) === 'wiki_proposal';
  }

}

<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Adapter;

use Drupal\aculta_cgu\Contract\ContributionAdapterInterface;
use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_wiki\Service\WikiProposalDecision;
use Drupal\aculta_wiki\Service\WikiProposalSubmission;
use Drupal\aculta_wiki\Service\WikiContributionMigrationGate;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Domain-owned policy and application for Wiki entry proposals.
 */
final class WikiContributionAdapter implements ContributionAdapterInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainSiteManager $siteManagers,
    private readonly EmailConfirmationPolicy $emailConfirmationPolicy,
    private readonly WikiProposalSubmission $proposalSubmission,
    private readonly WikiProposalDecision $proposalDecision,
    private readonly WikiContributionMigrationGate $migrationGate,
  ) {}

  /**
   * {@inheritdoc} */
  public function id(): string {
    return 'wiki';
  }

  /**
   * {@inheritdoc} */
  public function moduleId(): string {
    return 'aculta_wiki';
  }

  /**
   * {@inheritdoc} */
  public function purpose(?EntityInterface $target): string {
    return 'wiki';
  }

  /**
   * {@inheritdoc} */
  public function entityAccess(AccountInterface $account, Contribution $contribution, string $operation): AccessResultInterface {
    if ($contribution->get('source_module')->value !== 'aculta_wiki'
      || $contribution->get('purpose')->value !== 'wiki') {
      return AccessResult::forbidden()->cachePerUser()->cachePerPermissions();
    }
    if ($account->hasPermission('administer wiki proposals')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $owner = (int) $contribution->getOwnerId() === (int) $account->id();
    if (in_array($operation, ['view', 'view label', 'view revision'], TRUE)) {
      $viewAny = AccessResult::allowedIfHasPermission($account, 'view any wiki proposals');
      $viewOwn = AccessResult::allowedIf($owner)
        ->andIf(AccessResult::allowedIfHasPermission($account, 'view own wiki proposals'))
        ->cachePerUser();
      return $viewAny->orIf($viewOwn);
    }

    if ($operation === 'update') {
      $canReview = AccessResult::allowedIfHasPermission($account, 'moderate wiki proposals');
      $canRevise = AccessResult::allowedIf($owner && $contribution->get('state')->value === 'needs_changes')
        ->andIf(AccessResult::allowedIfHasPermission($account, 'submit wiki proposals'))
        ->cachePerUser();
      return $canReview->orIf($canRevise);
    }

    return AccessResult::forbidden()->cachePerUser()->cachePerPermissions();
  }

  /**
   * {@inheritdoc} */
  public function submitAccess(AccountInterface $account, string $intent, ?EntityInterface $target): bool {
    return $intent === 'update'
      && $account->isAuthenticated()
      && (int) $account->id() > 0
      && $this->siteManagers->getCurrentSiteKey()?->value === 'wiki'
      && ($account->hasPermission('submit wiki proposals')
        || $account->hasPermission('administer wiki proposals'))
      && $this->emailConfirmationPolicy->isConfirmed($account)
      && $this->migrationGate->isComplete()
      && $this->isEligibleTarget($account, $target, TRUE);
  }

  /**
   * {@inheritdoc} */
  public function reviewAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
    return $intent === 'update'
      && $this->siteManagers->getCurrentSiteKey()?->value === 'wiki'
      && $reviewer->hasPermission('moderate wiki proposals');
  }

  /**
   * {@inheritdoc} */
  public function applyAccess(AccountInterface $reviewer, string $intent, ?EntityInterface $target): bool {
    return $this->reviewAccess($reviewer, $intent, $target)
      && $reviewer->hasPermission('edit any wiki_entry content')
      && $this->isEligibleTarget($reviewer, $target, FALSE)
      && $target instanceof NodeInterface
      && $target->access('update', $reviewer);
  }

  /**
   * {@inheritdoc} */
  public function loadTarget(string $intent, ?string $targetUuid): ?EntityInterface {
    if ($intent !== 'update' || $targetUuid === NULL || $targetUuid === '') {
      return NULL;
    }
    $entities = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'uuid' => $targetUuid,
      'type' => 'wiki_entry',
    ]);
    $target = reset($entities);
    return $target instanceof NodeInterface ? $target : NULL;
  }

  /**
   * {@inheritdoc} */
  public function validatePayload(string $intent, array $payload, ?EntityInterface $target = NULL): array {
    if ($intent !== 'update') {
      throw new UnprocessableEntityHttpException('A Wiki aceita somente propostas de alteração.');
    }
    if (!$target instanceof NodeInterface) {
      throw new UnprocessableEntityHttpException('O verbete da proposta não está disponível.');
    }
    $domain = $this->wikiDomain();
    if ($domain === NULL) {
      throw new UnprocessableEntityHttpException('O domínio da Wiki não está disponível.');
    }
    return $this->proposalSubmission->validateProposedFields($payload, $target, $domain);
  }

  /**
   * {@inheritdoc} */
  public function validateSourceData(string $intent, array $sourceData): array {
    if ($intent !== 'update') {
      throw new UnprocessableEntityHttpException('A Wiki aceita somente propostas de alteração.');
    }
    return $this->proposalSubmission->validateProposalContext($sourceData);
  }

  /**
   * {@inheritdoc} */
  public function validateDecisionData(string $intent, array $selectedFields, array $decisionData): array {
    if ($intent !== 'update' || array_diff(array_keys($decisionData), ['list_modes']) !== []) {
      throw new UnprocessableEntityHttpException('As opções editoriais da Wiki são inválidas.');
    }
    $listModes = $decisionData['list_modes'] ?? [];
    if (!is_array($listModes)) {
      throw new UnprocessableEntityHttpException('As opções de aplicação de listas são inválidas.');
    }
    $listFields = ['field_wiki_references', 'field_wiki_categories', 'field_wiki_related'];
    foreach ($listModes as $field => $mode) {
      if (!is_string($field) || !in_array($field, $listFields, TRUE)
        || !in_array($field, $selectedFields, TRUE)
        || !in_array($mode, ['replace', 'append'], TRUE)) {
        throw new UnprocessableEntityHttpException('As opções de aplicação de listas são inválidas.');
      }
    }
    foreach (array_intersect($selectedFields, $listFields) as $field) {
      if (!isset($listModes[$field])) {
        throw new UnprocessableEntityHttpException('Escolha substituir ou acrescentar os itens da lista aprovada.');
      }
    }
    return ['list_modes' => $listModes];
  }

  /**
   * {@inheritdoc} */
  public function currentVersion(string $intent, ?EntityInterface $target): string {
    if ($intent !== 'update' || !$target instanceof NodeInterface) {
      return '';
    }
    return (string) $target->getRevisionId();
  }

  /**
   * {@inheritdoc}
   */
  public function recordDecision(Contribution $proposal, string $outcome): void {
    $this->proposalDecision->recordContributionOutcome($proposal, $outcome);
  }

  /**
   * {@inheritdoc} */
  public function apply(string $intent, ?EntityInterface $target, string $expectedVersion, array $selectedPayload, AccountInterface $reviewer, array $decisionData = []): EntityInterface {
    if ($intent !== 'update' || !$target instanceof NodeInterface || !ctype_digit($expectedVersion)) {
      throw new ConflictHttpException('O verbete-base da proposta não está disponível.');
    }
    $selectedFields = array_keys($selectedPayload);
    $normalized = $this->validateDecisionData($intent, $selectedFields, $decisionData);
    return $this->proposalDecision->applyToWikiEntry(
      $target,
      $expectedVersion,
      $selectedPayload,
      $selectedFields,
      $normalized,
    );
  }

  /**
   * Resolves the trusted Wiki domain from server-side purpose configuration. */
  private function wikiDomain(): ?DomainInterface {
    $domain = $this->siteManagers->getDomain(SiteKey::Wiki);
    return $domain instanceof DomainInterface ? $domain : NULL;
  }

  /**
   * Applies Wiki target eligibility without changing the owner's policy. */
  private function isEligibleTarget(AccountInterface $account, ?EntityInterface $target, bool $requirePublished): bool {
    $domain = $this->wikiDomain();
    if (!$target instanceof NodeInterface || !$domain
      || $target->bundle() !== 'wiki_entry' || !$target->isDefaultRevision()
      || ($requirePublished && !$target->isPublished())
      || !$target->hasField('field_domain_source')
      || $target->get('field_domain_source')->target_id !== $domain->id()) {
      return FALSE;
    }
    if ($requirePublished) {
      return $target->access('view', $account) && !$target->access('update', $account);
    }
    return TRUE;
  }

}

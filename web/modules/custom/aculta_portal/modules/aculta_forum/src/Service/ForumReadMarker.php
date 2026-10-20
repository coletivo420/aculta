<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\user\UserInterface;

/**
 * Marks accessible public Forum topics as read for the active account. */
final class ForumReadMarker {

  private const BATCH_SIZE = 200;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly TranslationInterface $stringTranslation,
    private readonly MessengerInterface $messenger,
  ) {}

  /**
   * Returns the number of currently accessible public topics. */
  public function countEligibleTopics(): int {
    if ($this->domainPurposeManager->getCurrentPurpose() !== 'forum') {
      return 0;
    }
    $domain = $this->domainPurposeManager->getDomain('forum');
    if ($domain === NULL) {
      return 0;
    }
    return (int) $this->topicQuery((string) $domain->id())->count()->execute();
  }

  /**
   * Marks one access-checked page of public topics during the Core batch.
   *
   * The official History API is procedural in Drupal 11.4's contrib History
   * module. It writes only the current account's History records, not Nodes.
   *
   * @param int $accountId
   *   Authenticated account captured when the user confirmed the action.
   * @param int $total
   *   Eligible topic count captured when the batch was created.
   * @param array $context
   *   Core Batch API context.
   */
  public function markBatch(int $accountId, int $total, array &$context): void {
    if ($this->currentUser->isAnonymous()
      || (int) $this->currentUser->id() !== $accountId) {
      $context['finished'] = 1;
      $context['message'] = $this->stringTranslation->translate('A conta mudou; nenhuma discussão adicional foi marcada.');
      return;
    }

    $account = $this->entityTypeManager->getStorage('user')->load($accountId);
    $domain = $this->domainPurposeManager->getDomain('forum');
    if (!$account instanceof UserInterface || $domain === NULL) {
      $context['finished'] = 1;
      return;
    }

    $processed = $this->domainPurposeManager->runInPurpose('forum', function () use ($domain, $account, $total, &$context): bool {
      if ($this->domainPurposeManager->getCurrentPurpose() !== 'forum') {
        $context['finished'] = 1;
        $context['message'] = $this->stringTranslation->translate('O contexto do Fórum não está disponível; nenhuma discussão adicional foi marcada.');
        return FALSE;
      }

      $lastId = (int) ($context['sandbox']['last_id'] ?? 0);
      $ids = $this->topicQuery((string) $domain->id())
        ->condition('nid', $lastId, '>')
        ->sort('nid', 'ASC')
        ->range(0, self::BATCH_SIZE)
        ->execute();

      if ($ids === []) {
        $context['finished'] = 1;
        $context['message'] = $this->stringTranslation->translate('Discussões marcadas como lidas.');
        return TRUE;
      }

      foreach ($ids as $id) {
        // history_write() is the installed History module's public API.
        history_write((int) $id, $account);
        $lastId = (int) $id;
        $context['results']['marked'] = (int) ($context['results']['marked'] ?? 0) + 1;
      }
      $context['sandbox']['last_id'] = $lastId;
      $context['finished'] = min(0.99, $lastId / max($total, $lastId + 1));
      $context['message'] = $this->stringTranslation->translate('Marcando discussões como lidas (@count processadas).', [
        '@count' => $context['results']['marked'],
      ]);
      return TRUE;
    });

    if ($processed === NULL) {
      $context['finished'] = 1;
      $context['message'] = $this->stringTranslation->translate('O contexto do Fórum não está disponível; nenhuma discussão adicional foi marcada.');
    }
  }

  /**
   * Reports the final result of the Core batch. */
  public function finishBatch(bool $success, array $results, array $operations, string $elapsed): void {
    if (!$success) {
      $this->messenger->addError($this->stringTranslation->translate('O histórico foi atualizado parcialmente. Você pode executar a ação novamente com segurança.'));
      return;
    }
    $count = (int) ($results['marked'] ?? 0);
    $this->messenger->addStatus($this->stringTranslation->translate('Foram marcadas @count discussões como lidas.', [
      '@count' => $count,
    ]));
  }

  /**
   * Builds the immutable public-topic query with current Node access. */
  private function topicQuery(string $domainId): QueryInterface {
    return $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_forum_topic')
      ->condition('status', 1)
      ->condition('field_domain_source.target_id', $domainId);
  }

}

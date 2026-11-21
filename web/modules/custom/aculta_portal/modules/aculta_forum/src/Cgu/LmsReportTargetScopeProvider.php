<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Cgu;

use Drupal\aculta_cgu\Scope\ReportTargetScopeProviderInterface;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Adaptador de escopo LMS para denúncias do CGU (purpose `lms`).
 *
 * Só libera a denúncia quando a conta tem a permissão `moderar denuncias lms` e o alvo
 * pertence a um contexto LMS cujo educador é ela mesma (Group). A decisão continua no
 * CGU; este adaptador não concede nenhuma permissão comunitária global.
 */
final class LmsReportTargetScopeProvider implements ReportTargetScopeProviderInterface {

  public const PERMISSION = 'moderar denuncias lms';

  public function __construct(
    private readonly DiscussionContextResolver $contextResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function supports(string $purpose): bool {
    return $purpose === 'lms';
  }

  /**
   * {@inheritdoc}
   */
  public function allowsTarget(AccountInterface $account, EntityInterface $target): bool {
    if (!$account->hasPermission(self::PERMISSION)) {
      return FALSE;
    }
    $host = $target instanceof CommentInterface ? $target->getCommentedEntity() : $target;
    if ($host === NULL) {
      return FALSE;
    }
    $context = $this->contextResolver->resolve($host, $account);
    if ($context === NULL || $context->getType() !== 'lms') {
      return FALSE;
    }
    return $context->getModerationAccess()->isAllowed();
  }

}

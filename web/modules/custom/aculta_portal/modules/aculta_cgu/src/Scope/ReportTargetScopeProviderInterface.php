<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Scope;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Adaptador de escopo por alvo para denúncias de um purpose restrito (ex.: LMS).
 *
 * O CGU continua dono da fila e da decisão. O adaptador responde apenas se a conta
 * pode moderar aquele alvo específico, por exemplo um educador do próprio Group.
 * Serviços que implementam esta interface são registrados com a tag
 * `aculta_cgu.report_target_scope_provider`.
 */
interface ReportTargetScopeProviderInterface {

  /**
   * Informa se o adaptador trata denúncias deste purpose.
   */
  public function supports(string $purpose): bool;

  /**
   * Informa se a conta pode moderar denúncias sobre este alvo concreto.
   */
  public function allowsTarget(AccountInterface $account, EntityInterface $target): bool;

}

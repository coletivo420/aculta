<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Associado;

use Drupal\Core\Session\AccountInterface;

/**
 * Consulta autoritativa da situação de associado ativo.
 *
 * Outros subkernels consomem este contrato em vez de ler a entidade
 * `aculta_associado` ou de inferir a situação por papel Drupal. A situação
 * `ativo` é a única que responde TRUE; pendente, convidado, suspenso,
 * desligado e conta ausente respondem FALSE. Nunca lança exceção por conta
 * inexistente.
 *
 * Quem consome deve também exigir a sua própria autorização (Entity Access,
 * Group, domínio/purpose). Este contrato não concede permissão Drupal.
 */
interface ActiveAssociateInterface {

  /**
   * Indica se a conta está ativa como associada.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Conta a consultar; contas anônimas sempre respondem FALSE.
   */
  public function isActiveAssociate(AccountInterface $account): bool;

  /**
   * Cache tags que mudam quando a situação desta conta muda.
   *
   * @return string[]
   *   Tags de cache, incluindo a lista de associados e a conta consultada.
   */
  public function associateCacheTags(AccountInterface $account): array;

}

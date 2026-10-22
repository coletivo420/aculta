<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\RestrictedForum;

use Drupal\aculta_user\Associado\ActiveAssociateInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Política de acesso a tópicos de fórum restrito a associados ativos.
 *
 * Acesso exige, simultaneamente: conta autenticada, situação `ativo` na
 * consulta autoritativa do `aculta_user` e membership válida em um dos Groups
 * restritos do tópico ou da sua categoria. Perder o status ativo revoga o
 * acesso imediatamente nesta checagem, mesmo antes de qualquer reconciliação
 * de membership.
 *
 * Tópicos sem relação com Group restrito recebem resultado neutro: o fórum
 * público continua regido pela política existente. Qualquer falha de consulta
 * nega o acesso (fail closed) e não revela título, existência ou motivo
 * detalhado.
 *
 * A política só acrescenta uma negativa: quando a conta pode ler, devolve
 * neutro para que as permissões, o estado de publicação e os grants do Core
 * continuem decidindo. Devolver `allowed` aqui concederia leitura de tópico
 * não publicado e ignoraria `access content`.
 */
class RestrictedForumAccessPolicy {

  public function __construct(
    private readonly RestrictedForumGroupResolverInterface $groupResolver,
    private readonly ActiveAssociateInterface $activeAssociate,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Avalia a operação sobre um tópico do Fórum.
   *
   * Só `view` é decidido aqui; outras operações continuam com a política de
   * entidade existente. Sem Group restrito, devolve neutro.
   */
  public function access(NodeInterface $topic, AccountInterface $account, string $operation): AccessResultInterface {
    if ($operation !== 'view') {
      return AccessResult::neutral();
    }

    try {
      $groupIds = $this->groupResolver->groupIdsForTopic($topic);
    }
    catch (\Throwable $e) {
      $this->logger->error('Falha ao resolver Groups restritos do tópico @nid: @message', [
        '@nid' => (string) $topic->id(),
        '@message' => $e->getMessage(),
      ]);
      return $this->result(FALSE, $topic, $account, 'Não foi possível validar o acesso ao fórum restrito.')
        ->setCacheMaxAge(0);
    }

    if ($groupIds === []) {
      return AccessResult::neutral();
    }

    $allowed = FALSE;
    try {
      $allowed = $this->activeAssociate->isActiveAssociate($account)
        && $this->hasMembership($account, $groupIds);
    }
    catch (\Throwable $e) {
      $this->logger->error('Falha ao revalidar associação/membership do tópico @nid: @message', [
        '@nid' => (string) $topic->id(),
        '@message' => $e->getMessage(),
      ]);
      return $this->result(FALSE, $topic, $account, 'Não foi possível validar o acesso ao fórum restrito.')
        ->setCacheMaxAge(0);
    }

    return $this->result($allowed, $topic, $account, $allowed ? NULL : 'Conteúdo restrito a associados ativos com acesso ao fórum.');
  }

  /**
   * Confere membership nos Groups restritos, sem confiar em papel Drupal.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Conta que pede acesso.
   * @param int[] $groupIds
   *   Groups que restringem o tópico.
   *
   * @return bool
   *   TRUE quando a conta é membro de ao menos um dos Groups.
   */
  private function hasMembership(AccountInterface $account, array $groupIds): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }
    foreach ($this->groupResolver->groupsOfType() as $gid => $group) {
      // getMember() devolve FALSE quando a conta não é membro; o Group usa a
      // consulta de membership do próprio módulo e o cache por requisição.
      if (in_array((int) $gid, $groupIds, TRUE) && $group->getMember($account) !== FALSE) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Resultado cacheável com as dependências de User, Group e configuração.
   *
   * Conceder aqui significa "sem objeção": o resultado é neutro para não
   * substituir a política do Core sobre publicação, permissões e grants.
   */
  private function result(bool $allowed, NodeInterface $topic, AccountInterface $account, ?string $reason = NULL): AccessResult {
    $result = $allowed ? AccessResult::neutral() : AccessResult::forbidden($reason ?? '');
    $result->addCacheableDependency($topic)
      ->addCacheContexts(['user', 'domain'])
      ->addCacheTags(array_merge(
        $this->activeAssociate->associateCacheTags($account),
        [
          'config:aculta_forum.settings',
          'group_relationship_list',
          'node_list',
        ],
      ));
    return $result;
  }

}

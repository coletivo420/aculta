<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_user\Forum\RestrictedForumGrants;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Fóruns restritos (ACULTA Fórum, F9, F-D14).
 *
 * O Core aplica o grant padrão a todo nó publicado e não permite negação por grant. Por isso a restrição tem três
 * pontos: grants por realm (node_access_records e node_grants), negação de acesso direto (entity_access) e filtro
 * das consultas com a tag node_access, que cobre listagens, contagens e busca. Motivo: nenhum desses pontos sozinho
 * fecha todas as saídas.
 */
final class RestrictedForumHooks implements ContainerInjectionInterface {

  /** Realms que restringem tópicos. */
  private const RESTRICTED_REALMS = [RestrictedForumGrants::REALM_EMAIL, RestrictedForumGrants::REALM_ASSOCIADO];

  private readonly RestrictedForumGrants $grants;

  public function __construct(
    private readonly AccountInterface $currentUser,
  ) {
    $this->grants = new RestrictedForumGrants();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('current_user'));
  }

  /**
   * Implements hook_node_grants().
   */
  #[Hook('node_grants')]
  public function nodeGrants(AccountInterface $account, string $op): array {
    return $this->grants->grantsFor($account, $op);
  }

  /**
   * Implements hook_node_access_records().
   */
  #[Hook('node_access_records')]
  public function nodeAccessRecords(NodeInterface $node): array {
    return $this->grants->recordsFor($node);
  }

  /**
   * Nega a visualização direta de tópico restrito a quem não tem o realm do nível do fórum.
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    if ($operation !== 'view' || $account->hasPermission('bypass node access')) {
      return NULL;
    }
    if ($entity instanceof TermInterface && $entity->bundle() === 'forums') {
      $exigidos = $this->realmsDoNivel($entity);
    }
    elseif ($entity instanceof NodeInterface) {
      $exigidos = $this->realmsRestritos($entity);
    }
    else {
      return NULL;
    }
    if ($exigidos === []) {
      return NULL;
    }
    $permitidos = array_keys($this->grants->grantsFor($account, 'view'));
    if (array_intersect($exigidos, $permitidos) !== []) {
      return NULL;
    }
    return AccessResult::forbidden('Fórum restrito.')
      ->addCacheableDependency($entity)
      ->addCacheContexts(['user.roles']);
  }

  /**
   * Filtra consultas com a tag node_access: tópico restrito some para quem não tem o realm.
   */
  #[Hook('query_node_access_alter')]
  public function queryNodeAccessAlter(AlterableInterface $query): void {
    $account = $query->getMetaData('account') ?: $this->currentUser;
    if ($account->hasPermission('bypass node access')) {
      return;
    }
    $permitidos = array_keys($this->grants->grantsFor($account, 'view'));
    $alias = $this->baseAlias($query);
    if ($alias === NULL) {
      return;
    }
    $restritos = self::RESTRICTED_REALMS;
    $permitidos = $permitidos === [] ? ['__nenhum__'] : $permitidos;
    $query->where(
      "{$alias}.nid NOT IN (SELECT na1.nid FROM {node_access} na1 WHERE na1.realm IN (:restritos[]) AND na1.nid NOT IN (SELECT na2.nid FROM {node_access} na2 WHERE na2.realm IN (:permitidos[]) AND na2.grant_view = 1))",
      [':restritos[]' => $restritos, ':permitidos[]' => $permitidos],
    );
  }

  /**
   * Alias, na consulta, da tabela que contém o nid do nó. Segue a tabela base declarada ou a primeira tabela de nó.
   */
  private function baseAlias(AlterableInterface $query): ?string {
    $tabela = $query->getMetaData('base_table');
    $tabelas = $query->getTables();
    if (!$tabela) {
      foreach ($tabelas as $info) {
        if (is_array($info) && in_array($info['table'] ?? NULL, ['node', 'node_field_data'], TRUE)) {
          $tabela = $info['table'];
          break;
        }
      }
    }
    foreach ($tabelas as $alias => $info) {
      if (is_array($info) && ($info['table'] ?? NULL) === $tabela) {
        return (string) $alias;
      }
    }
    return NULL;
  }

  /**
   * Realms exigidos pelo nível de visibilidade de um contêiner de fórum, ou vazio para público.
   */
  private function realmsDoNivel(TermInterface $term): array {
    if (!$term->hasField(RestrictedForumGrants::FIELD) || $term->get(RestrictedForumGrants::FIELD)->isEmpty()) {
      return [];
    }
    return match ((string) $term->get(RestrictedForumGrants::FIELD)->value) {
      'email_confirmado' => [RestrictedForumGrants::REALM_EMAIL],
      'associado' => [RestrictedForumGrants::REALM_ASSOCIADO],
      default => [],
    };
  }

  /**
   * Realms exigidos pelo nível do fórum do nó, ou vazio para público.
   */
  private function realmsRestritos(NodeInterface $node): array {
    return array_map(fn(array $r) => $r['realm'], $this->grants->recordsFor($node));
  }

  /**
   * Implements hook_taxonomy_term_update(). Mudança de nível refaz os grants dos tópicos.
   */
  #[Hook('taxonomy_term_update')]
  public function termUpdate(TermInterface $term): void {
    if ($term->bundle() !== 'forums' || !$term->hasField(RestrictedForumGrants::FIELD)) {
      return;
    }
    if (!$term->original || (string) $term->original->get(RestrictedForumGrants::FIELD)->value === (string) $term->get(RestrictedForumGrants::FIELD)->value) {
      return;
    }
    node_access_rebuild();
  }

}

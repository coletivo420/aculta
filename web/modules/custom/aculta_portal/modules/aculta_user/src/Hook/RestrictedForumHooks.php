<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_user\Forum\RestrictedForumGrants;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fóruns restritos (ACULTA Fórum, F9, F-D14).
 *
 * O Core aplica o grant padrão a todo nó publicado e não permite negação por grant. Por isso a restrição tem quatro
 * pontos: grants por realm (node_access_records e node_grants), negação de acesso direto (entity_access), filtro das
 * consultas com a tag node_access (listagens e contagens) e exclusão do sitemap. A conversão de 403 para 404 para
 * páginas restritas fica no subscriber RestrictedForumNotFoundSubscriber.
 */
final class RestrictedForumHooks {

  /** Realms que restringem conteúdo (o associado é marcador e não restringe, F-D19). */
  private const RESTRICTED_REALMS = [RestrictedForumGrants::REALM_EMAIL, RestrictedForumGrants::REALM_MARCADOR];

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_user.restricted_forum_grants')]
    private readonly RestrictedForumGrants $grants,
  ) {}

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
   * Nega a visualização direta de tópico ou contêiner restrito a quem não tem o realm exigido.
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    if ($operation !== 'view' || $account->hasPermission('bypass node access')) {
      return NULL;
    }
    if ($entity instanceof TermInterface && $entity->bundle() === 'forums') {
      $exigidos = $this->grants->realmsDoNivel($entity);
    }
    elseif ($entity instanceof NodeInterface) {
      $exigidos = $this->grants->realmsDoTopico($entity);
    }
    else {
      return NULL;
    }
    if ($this->grants->permite($account, $exigidos)) {
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
    $alias = $this->baseAlias($query);
    if ($alias === NULL) {
      return;
    }
    $permitidos = array_keys($this->grants->grantsFor($account, 'view')) ?: ['__nenhum__'];
    $query->where(
      "{$alias}.nid NOT IN (SELECT na1.nid FROM {node_access} na1 WHERE na1.realm IN (:restritos[]) AND na1.nid NOT IN (SELECT na2.nid FROM {node_access} na2 WHERE na2.realm IN (:permitidos[]) AND na2.grant_view = 1))",
      [':restritos[]' => self::RESTRICTED_REALMS, ':permitidos[]' => $permitidos],
    );
  }

  /**
   * Tira do sitemap os nós e contêineres restritos (F9).
   *
   * Motivo: o simple_sitemap gera a partir das entidades sem consultar grants.
   */
  #[Hook('simple_sitemap_links_alter')]
  public function simpleSitemapLinksAlter(array &$links, object $sitemap): void {
    foreach ($links as $key => $link) {
      $caminho = (string) ($link['meta']['path'] ?? '');
      if (!preg_match('#^(node|taxonomy/term)/(\d+)$#', $caminho, $m)) {
        continue;
      }
      $entidade = $this->entityTypeManager->getStorage($m[1] === 'node' ? 'node' : 'taxonomy_term')->load((int) $m[2]);
      if ($entidade instanceof NodeInterface && $this->grants->realmsDoTopico($entidade) !== []) {
        unset($links[$key]);
      }
      elseif ($entidade instanceof TermInterface && $this->grants->realmsDoNivel($entidade) !== []) {
        unset($links[$key]);
      }
    }
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

  /**
   * Alias, na consulta, da tabela que contém o nid do nó.
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

}

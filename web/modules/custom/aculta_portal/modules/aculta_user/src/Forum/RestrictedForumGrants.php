<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Forum;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Visibilidade dos fóruns por grants de nó (ACULTA Fórum, F9, F-D14).
 *
 * O nível de visibilidade é do fórum (termo do vocabulário forums, campo field_visibilidade). Cada tópico recebe
 * grants de visualização do nível do seu fórum; os demais tópicos mantêm o grant padrão do Core (público).
 * Motivo: as listagens do Forum, a busca e as contagens já passam pela tag node_access, então a restrição vale
 * em todos esses pontos sem consulta paralela.
 *
 * Realms: aculta_email_confirmado (papel email_confirmed e associado) e aculta_associado (papel associado).
 * Associados atravessam o nível de e-mail confirmado porque a concessão do papel é manual (F-D14).
 */
final class RestrictedForumGrants {

  public const REALM_EMAIL = 'aculta_email_confirmado';

  public const REALM_ASSOCIADO = 'aculta_associado';

  public const FIELD = 'field_visibilidade';

  /**
   * Grants de visualização para o usuário, por realm.
   */
  public function grantsFor(AccountInterface $account, string $op): array {
    if ($op !== 'view' || $account->isAnonymous()) {
      return [];
    }
    $roles = $account->getRoles();
    $grants = [];
    if (in_array('associado', $roles, TRUE) || in_array('email_confirmed', $roles, TRUE)) {
      $grants[self::REALM_EMAIL] = [1];
    }
    if (in_array('associado', $roles, TRUE)) {
      $grants[self::REALM_ASSOCIADO] = [1];
    }
    return $grants;
  }

  /**
   * Registros de acesso do tópico conforme o nível do fórum. Retorna vazio para público (grant padrão).
   */
  public function recordsFor(NodeInterface $node): array {
    if ($node->bundle() !== 'forum' || !$node->hasField('taxonomy_forums') || $node->get('taxonomy_forums')->isEmpty()) {
      return [];
    }
    $container = $node->get('taxonomy_forums')->entity;
    if ($container === NULL || !$container->hasField(self::FIELD) || $container->get(self::FIELD)->isEmpty()) {
      return [];
    }
    $nivel = (string) $container->get(self::FIELD)->value;
    $realm = match ($nivel) {
      'email_confirmado' => self::REALM_EMAIL,
      'associado' => self::REALM_ASSOCIADO,
      default => NULL,
    };
    if ($realm === NULL) {
      return [];
    }
    return [['realm' => $realm, 'gid' => 1, 'grant_view' => 1, 'grant_update' => 0, 'grant_delete' => 0, 'priority' => 0]];
  }

}

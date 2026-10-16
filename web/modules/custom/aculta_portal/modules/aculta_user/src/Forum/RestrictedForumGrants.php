<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Forum;

use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_user\Associado\AssociadoMarker;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Visibilidade dos fóruns restritos (ACULTA Fórum, F9, F-D14).
 *
 * Hierarquia de papéis para visualização (do menor ao maior):
 * authenticated < email_confirmed < associado < content_editor < administrator.
 * Cada nível enxerga o que o anterior enxerga. A hierarquia é aplicada pelos realms abaixo: o papel superior
 * recebe o realm do nível inferior. O administrador tem bypass de acesso a nós e não passa por esta regra.
 *
 * Realms: aculta_email_confirmado (email_confirmed ou associado) e aculta_associado (associado, ou content_editor
 * com email_confirmed). O content_editor atua como moderador do Fórum (ver docs/portal/PAPEIS-PARTICIPACAO.md).
 */
final class RestrictedForumGrants {

  /** Realm dos fóruns restritos a marcadores de associado (F-D20). */
  public const REALM_MARCADOR = 'aculta_marcador_associado';

  public function __construct(
    private readonly AssociadoMarker $marker,
  ) {}

  public const REALM_EMAIL = 'aculta_email_confirmado';

  public const FIELD = 'field_visibilidade';

  /**
   * Grants de visualização do usuário, por realm.
   */
  public function grantsFor(AccountInterface $account, string $op): array {
    if ($op !== 'view' || $account->isAnonymous()) {
      return [];
    }
    $roles = $account->getRoles();
    // content_editor só vale com e-mail confirmado (decisão de 2026-10-16). O moderador vê os níveis restritos.
    $emailConfirmado = in_array('email_confirmed', $roles, TRUE);
    $grants = [];
    if ($emailConfirmado) {
      $grants[self::REALM_EMAIL] = [1];
    }
    if ($this->marker->isAssociado((int) $account->id()) || ($emailConfirmado && in_array('content_editor', $roles, TRUE))) {
      $grants[self::REALM_MARCADOR] = [1];
    }
    return $grants;
  }

  /**
   * Registros de acesso do tópico conforme o nível do fórum. Vazio para público (grant padrão do Core).
   */
  public function recordsFor(NodeInterface $node): array {
    if ($node->bundle() !== 'forum' || !$node->hasField('taxonomy_forums') || $node->get('taxonomy_forums')->isEmpty()) {
      return [];
    }
    $container = $node->get('taxonomy_forums')->entity;
    if (!$container instanceof TermInterface) {
      return [];
    }
    return array_map(
      fn(string $realm) => ['realm' => $realm, 'gid' => 1, 'grant_view' => 1, 'grant_update' => 0, 'grant_delete' => 0, 'priority' => 0],
      $this->realmsDoNivel($container),
    );
  }

  /**
   * Realms exigidos pelo nível de visibilidade de um contêiner de fórum. Vazio para público.
   *
   * @return string[]
   */
  public function realmsDoNivel(TermInterface $container): array {
    if ($container->bundle() !== 'forums' || !$container->hasField(self::FIELD) || $container->get(self::FIELD)->isEmpty()) {
      return [];
    }
    return match ((string) $container->get(self::FIELD)->value) {
      'email_confirmado' => [self::REALM_EMAIL],
      'marcador_associado' => [self::REALM_MARCADOR],
      default => [],
    };
  }

  /**
   * Realms exigidos pelo nível do contêiner de um tópico. Vazio para público.
   *
   * @return string[]
   */
  public function realmsDoTopico(NodeInterface $node): array {
    return array_map(fn(array $r) => $r['realm'], $this->recordsFor($node));
  }

  /**
   * Indica se o usuário pode ver algo que exige os realms informados. Vazio exige nada.
   *
   * @param string[] $exigidos
   */
  public function permite(AccountInterface $account, array $exigidos): bool {
    if ($exigidos === []) {
      return TRUE;
    }
    return array_intersect($exigidos, array_keys($this->grantsFor($account, 'view'))) !== [];
  }

}

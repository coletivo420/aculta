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

  /** Permissão que libera os fóruns de nível "e-mail confirmado" (concedida por papel, em config). */
  public const PERM_EMAIL = 'view aculta restricted forum email confirmed';

  /** Realm dos fóruns de nível "estudante". */
  public const REALM_ESTUDANTE = 'aculta_estudante';

  /** Permissão que libera os fóruns de nível "estudante" (concedida por papel, em config). */
  public const PERM_ESTUDANTE = 'view aculta restricted forum estudante';

  /** Papel de associado (nível 4 da hierarquia). */
  public const ROLE_ASSOCIADO = 'associado';

  /** Permissão que libera os fóruns de nível "marcador de associado" (concedida por papel, em config). */
  public const PERM_MARCADOR = 'view aculta restricted forum associado';

  public const FIELD = 'field_visibilidade';

  /**
   * Grants de visualização do usuário, por realm.
   *
   * Os realms vêm das permissões explícitas. Marcador de associado é um vínculo da conta (AssociadoMarker), não um
   * papel, e continua valendo por si só. Regra composta: a permissão de moderação do content_editor só vale com
   * e-mail confirmado (decisão de 2026-10-16), por isso essa verificação fica no código e não na config.
   */
  public function grantsFor(AccountInterface $account, string $op): array {
    if ($op !== 'view' || $account->isAnonymous()) {
      return [];
    }
    $roles = $account->getRoles();
    $emailConfirmado = in_array('email_confirmed', $roles, TRUE);
    // Regra composta: moderador (content_editor) sem e-mail confirmado não recebe os níveis por permissão.
    // O marcador de associado é vínculo da conta e não depende dessa regra.
    $moderadorSemEmail = in_array('content_editor', $roles, TRUE) && !$emailConfirmado;
    $grants = [];
    if (!$moderadorSemEmail && $account->hasPermission(self::PERM_EMAIL)) {
      $grants[self::REALM_EMAIL] = [1];
    }
    if (!$moderadorSemEmail && $account->hasPermission(self::PERM_ESTUDANTE)) {
      $grants[self::REALM_ESTUDANTE] = [1];
    }
    // Associado: papel concedido pelo administrador (decisão de 2026-10-17). O marcador de conta é mantido como fallback
    // enquanto não houver decisão de remoção; no Runtime não há marcas a migrar.
    $associado = in_array(self::ROLE_ASSOCIADO, $roles, TRUE) || $this->marker->isAssociado((int) $account->id());
    if ($associado || ($emailConfirmado && $account->hasPermission(self::PERM_MARCADOR))) {
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
      'estudante' => [self::REALM_ESTUDANTE],
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

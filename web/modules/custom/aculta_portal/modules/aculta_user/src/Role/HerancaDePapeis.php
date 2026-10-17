<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Role;

use Drupal\aculta_portal\Role\RoleHierarchy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Herança de papéis do ACULTA: substitui o módulo contrib role_inheritance.
 *
 * O mapa vem de aculta_portal.role_hierarchy (cadeia e laterais), com a função pura RoleHierarchy. Os papéis herdados
 * são somados à conta em memória ao carregá-la (user_load) e retirados ao salvá-la (user_presave), para que nunca sejam
 * gravados como papel direto. O usuário logado recebe a mesma extensão (RoleInheritanceCurrentUserSubscriber).
 */
final class HerancaDePapeis {

  private const PAPEIS_BASICOS = ['anonymous', 'authenticated'];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {}

  /**
   * Mapa de herança derivado da cadeia atual.
   *
   * @return array<string, string[]>
   */
  public function mapa(): array {
    $configuracao = $this->configFactory->get('aculta_portal.role_hierarchy');
    $todos = array_keys($this->entityTypeManager->getStorage('user_role')->loadMultiple());
    return RoleHierarchy::derivarMapa(
      (array) ($configuracao->get('cadeia') ?? []),
      (array) ($configuracao->get('laterais') ?? []),
      $todos,
    );
  }

  /**
   * Papéis herdados (sem os básicos) dos papéis informados.
   *
   * @param string[] $papeis
   *
   * @return string[]
   */
  public function herdados(array $papeis): array {
    return array_values(array_diff(RoleHierarchy::herdados($papeis, $this->mapa()), self::PAPEIS_BASICOS));
  }

  /**
   * Papéis gravados diretamente na conta (sem herança), lidos do banco.
   *
   * @return string[]
   */
  public function papeisDiretos(int $uid): array {
    if ($uid <= 0) {
      return [];
    }
    return array_values(array_map('strval', $this->database->select('user__roles', 'r')
      ->fields('r', ['roles_target_id'])
      ->condition('entity_id', $uid)
      ->execute()
      ->fetchCol()));
  }

  /**
   * Papéis herdados que não foram gravados diretamente na conta: são os que a conta não deve salvar.
   *
   * @return string[]
   */
  public function herdadosNaoGravados(int $uid): array {
    $diretos = $this->papeisDiretos($uid);
    return array_values(array_diff($this->herdados($diretos), $diretos));
  }

  /**
   * Conta a que se aplica a herança (o usuário logado precisa do mesmo cálculo).
   *
   * @return string[]
   */
  public function papeisEstendidos(AccountInterface $conta): array {
    return array_values(array_unique(array_merge($conta->getRoles(), $this->herdados($conta->getRoles()))));
  }

}

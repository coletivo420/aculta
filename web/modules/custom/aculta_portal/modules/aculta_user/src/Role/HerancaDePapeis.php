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
class HerancaDePapeis {

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
    $existentes = array_values(array_diff($todos, ['anonymous', 'administrator']));
    $completo = RoleHierarchy::completar((array) ($configuracao->get('cadeia') ?? []), (array) ($configuracao->get('laterais') ?? []), $existentes);
    return RoleHierarchy::derivarMapa($completo['cadeia'], $completo['laterais'], $todos);
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
   * Remove o papel excluído da configuração da cadeia (equivale à limpeza do mapa no hook de exclusão do contrib).
   */
  public function removerPapel(string $papel): void {
    $configuracao = $this->configFactory->getEditable('aculta_portal.role_hierarchy');
    $todos = array_keys($this->entityTypeManager->getStorage('user_role')->loadMultiple());
    $existentes = array_values(array_diff($todos, ['anonymous', 'administrator', $papel]));
    $cadeia = array_values(array_diff((array) $configuracao->get('cadeia'), [$papel]));
    $laterais = (array) $configuracao->get('laterais');
    unset($laterais[$papel]);
    $completo = RoleHierarchy::completar($cadeia, $laterais, $existentes);
    $configuracao->set('cadeia', $completo['cadeia'])->set('laterais', $completo['laterais'])->save();
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

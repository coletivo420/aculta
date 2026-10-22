<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Associado;

use Drupal\aculta_user\Entity\AssociadoMarcador;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\profile\ProfileStorageInterface;
use Drupal\user\UserInterface;

/**
 * Situação de associado: pedido, concessão, suspensão e desligamento.
 *
 * Só o administrador altera a situação. Situações: pendente (pedido da
 * pessoa), convidado (convite do administrador, aguardando aceite), ativo,
 * suspenso e desligado. A data de adesão é a primeira ativação e não muda
 * depois. Recusar um pedido ou convite remove a linha. Idempotente: repetir a
 * mesma situação não faz nada.
 */
final class AssociadoMarker implements ActiveAssociateInterface {

  public const PENDENTE = 'pendente';
  public const ATIVO = 'ativo';
  public const SUSPENSO = 'suspenso';
  public const DESLIGADO = 'desligado';
  public const CONVIDADO = 'convidado';

  /**
   * Situações que o administrador pode definir (recusar é à parte).
   */
  public const SITUACOES = [self::ATIVO, self::SUSPENSO, self::DESLIGADO];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Verdadeiro se a conta está ativa como associado.
   */
  public function isAssociado(int $uid): bool {
    return $this->situacao($uid) === self::ATIVO;
  }

  /**
   * Consulta autoritativa do contrato compartilhado; contas anônimas são FALSE.
   */
  public function isActiveAssociate(AccountInterface $account): bool {
    return !$account->isAnonymous() && $this->isAssociado((int) $account->id());
  }

  /**
   * {@inheritdoc}
   */
  public function associateCacheTags(AccountInterface $account): array {
    return array_values(array_unique([
      'aculta_associado_list',
      $account->isAnonymous() ? 'user:0' : 'user:' . (int) $account->id(),
    ]));
  }

  /**
   * Situação atual da conta, ou NULL se nunca pediu adesão. */
  public function situacao(int $uid): ?string {
    $marca = $this->marcaDe($uid);
    return $marca === NULL ? NULL : (string) $marca->get('situacao')->value;
  }

  /**
   * Data de adesão (primeira ativação), ou NULL. */
  public function dataAdesao(int $uid): ?int {
    $marca = $this->marcaDe($uid);
    $valor = $marca?->get('data_adesao')->value;
    return $valor === NULL || $valor === '' ? NULL : (int) $valor;
  }

  /**
   * Uids ativos, em ordem crescente. */
  public function uids(): array {
    $storage = $this->entityTypeManager->getStorage('aculta_associado');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('situacao', self::ATIVO)->sort('uid')->execute();
    $uids = [];
    foreach ($storage->loadMultiple($ids) as $marca) {
      if ($marca instanceof AssociadoMarcador) {
        $uids[] = (int) $marca->get('uid')->target_id;
      }
    }
    return $uids;
  }

  /**
   * Contas com situação definida, com a data de adesão.
   *
   * @return array<int, array{uid: int, situacao: string, data_adesao: ?int}>
   *   Linhas de ativo, suspenso e desligado, em ordem de uid.
   */
  public function listadas(): array {
    $storage = $this->entityTypeManager->getStorage('aculta_associado');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('situacao', self::PENDENTE, '<>')->sort('uid')->execute();
    $linhas = [];
    foreach ($storage->loadMultiple($ids) as $marca) {
      if (!$marca instanceof AssociadoMarcador) {
        continue;
      }
      $linhas[] = [
        'uid' => (int) $marca->get('uid')->target_id,
        'situacao' => (string) $marca->get('situacao')->value,
        'data_adesao' => $marca->get('data_adesao')->value === NULL ? NULL : (int) $marca->get('data_adesao')->value,
      ];
    }
    return $linhas;
  }

  /**
   * Pedidos em análise, mais antigos primeiro.
   *
   * @return int[]
   *   Uids com pedido pendente.
   */
  public function pendentes(): array {
    $storage = $this->entityTypeManager->getStorage('aculta_associado');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('situacao', self::PENDENTE)->sort('created')->execute();
    $uids = [];
    foreach ($storage->loadMultiple($ids) as $marca) {
      if ($marca instanceof AssociadoMarcador) {
        $uids[] = (int) $marca->get('uid')->target_id;
      }
    }
    return $uids;
  }

  /**
   * Pedido de adesão: só cria o pedido, não concede nada.
   *
   * @return bool
   *   TRUE se o pedido foi criado agora.
   */
  public function pedir(int $uid): bool {
    if ($uid <= 0) {
      return FALSE;
    }
    $marca = $this->marcaDe($uid);
    // Quem foi desligado pode pedir de novo; a data de adesão é mantida.
    if ($marca !== NULL && (string) $marca->get('situacao')->value !== self::DESLIGADO) {
      return FALSE;
    }
    if ($marca === NULL) {
      $marca = $this->criarMarca($uid);
    }
    $marca->set('situacao', self::PENDENTE);
    $marca->save();
    $this->invalidar($uid);
    return TRUE;
  }

  /**
   * Define a situação (ação do administrador).
   *
   * A primeira ativação grava a data de adesão.
   *
   * @return bool
   *   TRUE se a situação mudou.
   */
  public function definir(int $uid, string $situacao, int $alteradoPor, string $motivo = ''): bool {
    if ($uid <= 0 || $alteradoPor <= 0 || !in_array($situacao, self::SITUACOES, TRUE)) {
      return FALSE;
    }
    $marca = $this->marcaDe($uid);
    if ($marca === NULL) {
      $marca = $this->criarMarca($uid, ['situacao' => self::PENDENTE]);
    }
    if ((string) $marca->get('situacao')->value === $situacao) {
      return FALSE;
    }
    $marca->set('situacao', $situacao);
    if ($situacao === self::ATIVO && $marca->get('data_adesao')->isEmpty()) {
      $marca->set('data_adesao', $this->time->getRequestTime());
    }
    $marca->set('concedido_por', $alteradoPor);
    $marca->set('motivo', mb_substr(trim($motivo), 0, 2000));
    $marca->save();
    if ($situacao === self::ATIVO) {
      $this->garantirPerfil($uid);
    }
    $this->invalidar($uid);
    return TRUE;
  }

  /**
   * Garante o perfil de associado, onde ficam dados como RG.
   *
   * Sem papel, o perfil não é criado pelo papel; então a ativação cria o
   * perfil se ainda não existe.
   */
  private function garantirPerfil(int $uid): void {
    $storage = $this->entityTypeManager->getStorage('profile');
    $conta = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$storage instanceof ProfileStorageInterface || !$conta instanceof UserInterface) {
      return;
    }
    if ($storage->loadByUser($conta, 'associado') === NULL) {
      $storage->create(['type' => 'associado', 'uid' => $uid])->save();
    }
  }

  /**
   * Convite do administrador; a pessoa aceita ou recusa em Minha conta.
   *
   * Não concede nada antes do aceite. Quem já é ativo ou suspenso não recebe
   * convite.
   *
   * @return bool
   *   TRUE se o convite foi registrado agora.
   */
  public function convidar(int $uid, int $alteradoPor, string $motivo = ''): bool {
    if ($uid <= 0 || $alteradoPor <= 0) {
      return FALSE;
    }
    $marca = $this->marcaDe($uid) ?? $this->criarMarca($uid);
    $atual = $marca->isNew() ? NULL : (string) $marca->get('situacao')->value;
    if (in_array($atual, [self::ATIVO, self::SUSPENSO, self::CONVIDADO], TRUE)) {
      return FALSE;
    }
    $marca->set('situacao', self::CONVIDADO);
    $marca->set('concedido_por', $alteradoPor);
    $marca->set('motivo', mb_substr(trim($motivo), 0, 2000));
    $marca->save();
    $this->invalidar($uid);
    return TRUE;
  }

  /**
   * A pessoa aceita o convite: vira ativo, com data de adesão e perfil.
   *
   * @return bool
   *   TRUE se o convite foi aceito agora.
   */
  public function aceitarConvite(int $uid): bool {
    $marca = $this->marcaDe($uid);
    if ($marca === NULL || (string) $marca->get('situacao')->value !== self::CONVIDADO) {
      return FALSE;
    }
    $marca->set('situacao', self::ATIVO);
    if ($marca->get('data_adesao')->isEmpty()) {
      $marca->set('data_adesao', $this->time->getRequestTime());
    }
    $marca->save();
    $this->garantirPerfil($uid);
    $this->invalidar($uid);
    return TRUE;
  }

  /**
   * A pessoa recusa o convite: a linha é removida.
   *
   * @return bool
   *   TRUE se havia convite.
   */
  public function recusarConvite(int $uid): bool {
    $marca = $this->marcaDe($uid);
    if ($marca === NULL || (string) $marca->get('situacao')->value !== self::CONVIDADO) {
      return FALSE;
    }
    $marca->delete();
    $this->invalidar($uid);
    return TRUE;
  }

  /**
   * Recusa um pedido pendente (remove a linha).
   *
   * @return bool
   *   TRUE se havia pedido pendente.
   */
  public function recusar(int $uid): bool {
    $marca = $this->marcaDe($uid);
    if ($marca === NULL || (string) $marca->get('situacao')->value !== self::PENDENTE) {
      return FALSE;
    }
    $marca->delete();
    $this->invalidar($uid);
    return TRUE;
  }

  /**
   * Mudança de situação invalida listagens e páginas dependentes da conta.
   */
  private function invalidar(int $uid): void {
    Cache::invalidateTags(['user:' . $uid, 'node_list', 'taxonomy_term_list', 'aculta_associado_list']);
  }

  /**
   * Marca de associado da conta, ou NULL se nunca pediu adesão.
   */
  private function marcaDe(int $uid): ?AssociadoMarcador {
    if ($uid <= 0) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('aculta_associado');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('uid', $uid)->range(0, 1)->execute();
    if ($ids === []) {
      return NULL;
    }
    $marca = $storage->load(reset($ids));
    return $marca instanceof AssociadoMarcador ? $marca : NULL;
  }

  /**
   * Cria a marca de associado da conta, ainda não gravada.
   *
   * @param int $uid
   *   Conta dona da marca.
   * @param array<string, mixed> $values
   *   Valores iniciais além do uid.
   */
  private function criarMarca(int $uid, array $values = []): AssociadoMarcador {
    $marca = $this->entityTypeManager->getStorage('aculta_associado')->create($values + ['uid' => $uid]);
    if (!$marca instanceof AssociadoMarcador) {
      throw new \LogicException('A entidade aculta_associado não é AssociadoMarcador.');
    }
    return $marca;
  }

}

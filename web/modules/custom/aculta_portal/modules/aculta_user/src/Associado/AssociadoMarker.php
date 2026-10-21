<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Associado;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Situação de associado: pedido de adesão, concessão, suspensão e desligamento. Só o administrador altera a situação.
 *
 * Situações: pendente (pedido em análise), ativo, suspenso e desligado. A data de adesão é a primeira ativação e não
 * muda depois. Recusar um pedido remove a linha pendente. Idempotente: repetir a mesma situação não faz nada.
 */
final class AssociadoMarker {

  public const PENDENTE = 'pendente';
  public const ATIVO = 'ativo';
  public const SUSPENSO = 'suspenso';
  public const DESLIGADO = 'desligado';

  /** Situações que o administrador pode definir (recusar é tratado à parte). */
  public const SITUACOES = [self::ATIVO, self::SUSPENSO, self::DESLIGADO];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /** Verdadeiro se a conta está ativa como associado. */
  public function isAssociado(int $uid): bool {
    return $this->situacao($uid) === self::ATIVO;
  }

  /** Situação atual da conta, ou NULL se nunca pediu adesão. */
  public function situacao(int $uid): ?string {
    $marca = $this->marcaDe($uid);
    return $marca === NULL ? NULL : (string) $marca->get('situacao')->value;
  }

  /** Data de adesão (primeira ativação), ou NULL. */
  public function dataAdesao(int $uid): ?int {
    $marca = $this->marcaDe($uid);
    $valor = $marca?->get('data_adesao')->value;
    return $valor === NULL || $valor === '' ? NULL : (int) $valor;
  }

  /** Uids ativos, em ordem crescente. */
  public function uids(): array {
    $ids = $this->entityTypeManager->getStorage('aculta_associado')->getQuery()->accessCheck(FALSE)
      ->condition('situacao', self::ATIVO)->sort('uid')->execute();
    $marcas = $this->entityTypeManager->getStorage('aculta_associado')->loadMultiple($ids);
    return array_values(array_map(static fn ($m) => (int) $m->get('uid')->target_id, $marcas));
  }

  /**
   * Contas com situação definida (ativo, suspenso ou desligado), com a data de adesão.
   *
   * @return array<int, array{uid: int, situacao: string, data_adesao: ?int}>
   */
  public function listadas(): array {
    $ids = $this->entityTypeManager->getStorage('aculta_associado')->getQuery()->accessCheck(FALSE)
      ->condition('situacao', self::PENDENTE, '<>')->sort('uid')->execute();
    $linhas = [];
    foreach ($this->entityTypeManager->getStorage('aculta_associado')->loadMultiple($ids) as $marca) {
      $linhas[] = [
        'uid' => (int) $marca->get('uid')->target_id,
        'situacao' => (string) $marca->get('situacao')->value,
        'data_adesao' => $marca->get('data_adesao')->value === NULL ? NULL : (int) $marca->get('data_adesao')->value,
      ];
    }
    return $linhas;
  }

  /** Pedidos em análise, mais antigos primeiro. @return int[] uids */
  public function pendentes(): array {
    $ids = $this->entityTypeManager->getStorage('aculta_associado')->getQuery()->accessCheck(FALSE)
      ->condition('situacao', self::PENDENTE)->sort('created')->execute();
    $marcas = $this->entityTypeManager->getStorage('aculta_associado')->loadMultiple($ids);
    return array_values(array_map(static fn ($m) => (int) $m->get('uid')->target_id, $marcas));
  }

  /** Pedido de adesão. Só cria o pedido; não concede nada. Retorna TRUE se criou agora. */
  public function pedir(int $uid): bool {
    if ($uid <= 0) {
      return FALSE;
    }
    $marca = $this->marcaDe($uid);
    // Quem foi desligado pode pedir de novo; a data de adesão original é mantida.
    if ($marca !== NULL && (string) $marca->get('situacao')->value !== self::DESLIGADO) {
      return FALSE;
    }
    if ($marca === NULL) {
      $marca = $this->entityTypeManager->getStorage('aculta_associado')->create(['uid' => $uid]);
    }
    $marca->set('situacao', self::PENDENTE);
    $marca->save();
    $this->invalidar($uid);
    return TRUE;
  }

  /**
   * Define a situação (ação do administrador). Ativar pela primeira vez grava a data de adesão.
   * Retorna TRUE se mudou.
   */
  public function definir(int $uid, string $situacao, int $alteradoPor, string $motivo = ''): bool {
    if ($uid <= 0 || $alteradoPor <= 0 || !in_array($situacao, self::SITUACOES, TRUE)) {
      return FALSE;
    }
    $storage = $this->entityTypeManager->getStorage('aculta_associado');
    $marca = $this->marcaDe($uid);
    if ($marca === NULL) {
      $marca = $storage->create(['uid' => $uid, 'situacao' => self::PENDENTE]);
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
   * Associado ativo tem o perfil de associado (dados como RG ficam nele). Sem papel, o perfil não é criado pelo papel,
   * então a ativação cria o perfil se ainda não existe.
   */
  private function garantirPerfil(int $uid): void {
    $storage = $this->entityTypeManager->getStorage('profile');
    $conta = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($conta instanceof \Drupal\user\UserInterface && $storage->loadByUser($conta, 'associado') === NULL) {
      $storage->create(['type' => 'associado', 'uid' => $uid])->save();
    }
  }

  /** Recusa um pedido pendente (remove a linha). Retorna TRUE se havia pedido pendente. */
  public function recusar(int $uid): bool {
    $marca = $this->marcaDe($uid);
    if ($marca === NULL || (string) $marca->get('situacao')->value !== self::PENDENTE) {
      return FALSE;
    }
    $marca->delete();
    $this->invalidar($uid);
    return TRUE;
  }

  /** Mudança de situação: listagens e páginas que dependem do acesso da conta são recalculadas. */
  private function invalidar(int $uid): void {
    Cache::invalidateTags(['user:' . $uid, 'node_list', 'taxonomy_term_list', 'aculta_associado_list']);
  }

  private function marcaDe(int $uid): ?object {
    if ($uid <= 0) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('aculta_associado');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('uid', $uid)->range(0, 1)->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

}

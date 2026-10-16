<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Legacy;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Cria ou atualiza a conta de uma autoria histórica do Fórum (ACULTA Fórum, F2).
 *
 * Idempotente pela origem: a mesma origem nunca gera duas contas, e reexecutar a importação só atualiza o nome
 * histórico. A conta nasce bloqueada, com o papel usuarios_legados_forum, sem e-mail, sem apelido público e
 * com perfil privado. Não envia mensagem, não concede conquista e não entra em sorteio de avatar.
 *
 * Motivo: a importação (F3) chama este serviço, e as regras de bloqueio ficam em um único lugar. O histórico
 * fica no perfil participante, sem tabela de autores paralela (decisão F-D06/F-D07).
 */
final class LegacyAccountManager {

  public const ROLE = 'usuarios_legados_forum';

  public const PROFILE_TYPE = 'participante';

  public const USERNAME_PREFIX = 'legado-';

  /** Limite do nome técnico da conta e do nome histórico (campo string do perfil). */
  private const NAME_MAX = 255;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {}

  /**
   * Indica se a origem tem formato aceito: identificador estável e ASCII, sem espaços.
   */
  public static function isValidSourceId(string $sourceId): bool {
    return (bool) preg_match('/^[A-Za-z0-9._-]{1,64}$/', $sourceId);
  }

  /**
   * Nome técnico da conta derivado da origem. Estável e único para cada origem válida.
   */
  public static function usernameFor(string $sourceId): string {
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $sourceId) ?? '');
    return self::USERNAME_PREFIX . substr($slug, 0, 40);
  }

  /**
   * Cria ou atualiza a autoria. Retorna o uid e se a conta foi criada agora.
   *
   * @throws \InvalidArgumentException Origem fora do formato, nome vazio ou longo demais, ou nome técnico ocupado
   *   por conta que não é desta origem.
   */
  public function upsert(string $sourceId, string $displayName): array {
    if (!self::isValidSourceId($sourceId)) {
      throw new \InvalidArgumentException('Origem fora do formato aceito.');
    }
    $nome = trim($displayName);
    if ($nome === '' || mb_strlen($nome) > self::NAME_MAX || preg_match('/[\x00-\x1F\x7F]/', $nome)) {
      throw new \InvalidArgumentException('Nome histórico vazio, longo demais ou com caracteres de controle.');
    }

    $transaction = $this->database->startTransaction();
    try {
      $profile = $this->findProfile($sourceId);
      if ($profile !== NULL) {
        if ((string) $profile->get('field_nome_historico')->value !== $nome) {
          $profile->set('field_nome_historico', $nome);
          $profile->save();
        }
        return ['uid' => (int) $profile->getOwnerId(), 'created' => FALSE];
      }

      $name = self::usernameFor($sourceId);
      $ocupado = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $name]);
      if ($ocupado) {
        throw new \InvalidArgumentException('Nome técnico ocupado por outra conta.');
      }

      $account = User::create([
        'name' => $name,
        'status' => 0,
        'roles' => [self::ROLE],
      ]);
      $account->save();
      $this->createProfile($account, $sourceId, $nome);
      return ['uid' => (int) $account->id(), 'created' => TRUE];
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Perfil participante da origem, se já existir.
   */
  private function findProfile(string $sourceId): ?ProfileInterface {
    $ids = $this->entityTypeManager->getStorage('profile')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::PROFILE_TYPE)
      ->condition('field_forum_origem_id', $sourceId)
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    return $this->entityTypeManager->getStorage('profile')->load(reset($ids));
  }

  private function createProfile(UserInterface $account, string $sourceId, string $nome): void {
    $profile = $this->entityTypeManager->getStorage('profile')->create([
      'type' => self::PROFILE_TYPE,
      'uid' => $account->id(),
      'field_nome_historico' => $nome,
      'field_forum_origem_id' => $sourceId,
    ]);
    if ($profile->hasField('field_perfil_publico')) {
      $profile->set('field_perfil_publico', FALSE);
    }
    $profile->save();
  }

}

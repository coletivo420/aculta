<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Legacy;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Cria ou atualiza uma autoria histórica do Fórum (ACULTA Fórum, F2).
 *
 * Idempotente pela origem: a mesma origem nunca gera duas autorias, e reexecutar a importação só atualiza o nome.
 * Não cria conta de usuário, não envia mensagem e não concede conquista. A autoria fica na entidade própria
 * aculta_legado_autor, separada da identidade User (decisão de 2026-10-16).
 */
final class LegacyAuthorManager {

  /** Limite do nome histórico. */
  private const NAME_MAX = 255;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Indica se a origem tem formato aceito: identificador estável, ASCII, sem espaços.
   */
  public static function isValidSourceId(string $sourceId): bool {
    return (bool) preg_match('/^[A-Za-z0-9._-]{1,64}$/', $sourceId);
  }

  /**
   * Cria ou atualiza a autoria. Retorna o id da entidade e se foi criada agora.
   *
   * @throws \InvalidArgumentException Origem fora do formato ou nome vazio, longo demais ou com caracteres de controle.
   */
  public function upsert(string $sourceId, string $displayName): array {
    if (!self::isValidSourceId($sourceId)) {
      throw new \InvalidArgumentException('Origem fora do formato aceito.');
    }
    $nome = trim($displayName);
    if ($nome === '' || mb_strlen($nome) > self::NAME_MAX || preg_match('/[\x00-\x1F\x7F]/', $nome)) {
      throw new \InvalidArgumentException('Nome histórico vazio, longo demais ou com caracteres de controle.');
    }

    $storage = $this->entityTypeManager->getStorage('aculta_legado_autor');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('origem_id', $sourceId)->range(0, 1)->execute();
    if ($ids) {
      $autoria = $storage->load(reset($ids));
      if ((string) $autoria->get('nome')->value !== $nome) {
        $autoria->set('nome', $nome);
        $autoria->save();
      }
      return ['id' => (int) $autoria->id(), 'created' => FALSE];
    }

    $autoria = $storage->create(['origem_id' => $sourceId, 'nome' => $nome]);
    $autoria->save();
    return ['id' => (int) $autoria->id(), 'created' => TRUE];
  }

}

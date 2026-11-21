<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_legacy\Author;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Cria ou atualiza uma autoria histórica do fórum legado.
 *
 * Idempotente pela origem: reexecutar a importação só atualiza o nome.
 * Não cria conta, envia mensagem nem concede conquista. A autoria fica em
 * aculta_forum_legacy_user, separada da identidade User (F-D44).
 */
final class LegacyForumUserManager {

  /**
   * Limite do nome histórico. */
  private const NAME_MAX = 255;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Indica se a origem tem formato estável, ASCII e sem espaços.
   */
  public static function isValidSourceId(string $sourceId): bool {
    return (bool) preg_match('/^[A-Za-z0-9._-]{1,64}$/', $sourceId);
  }

  /**
   * Cria ou atualiza uma autoria histórica.
   *
   * @return array{ id: int, created: bool }
   *   ID da entidade e indicação de criação nesta chamada.
   *
   * @throws \InvalidArgumentException
   *   Origem inválida ou nome vazio, longo ou com caracteres de controle.
   */
  public function upsert(string $sourceId, string $displayName): array {
    if (!self::isValidSourceId($sourceId)) {
      throw new \InvalidArgumentException('Origem fora do formato aceito.');
    }
    $nome = trim($displayName);
    if ($nome === '' || mb_strlen($nome) > self::NAME_MAX
      || preg_match('/[\x00-\x1F\x7F]/', $nome)) {
      throw new \InvalidArgumentException('Nome histórico vazio, longo demais ou com caracteres de controle.');
    }

    $storage = $this->entityTypeManager->getStorage('aculta_forum_legacy_user');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('origem_id', $sourceId)->range(0, 1)->execute();
    if ($ids) {
      $autoria = $storage->load(reset($ids));
      if ($autoria instanceof ContentEntityInterface && $this->textValue($autoria->get('nome')->value) !== $nome) {
        $autoria->set('nome', $nome);
        $autoria->save();
      }
      return ['id' => $this->intValue($autoria?->id()), 'created' => FALSE];
    }

    $autoria = $storage->create(['origem_id' => $sourceId, 'nome' => $nome]);
    $autoria->save();
    return ['id' => (int) $autoria->id(), 'created' => TRUE];
  }

  /**
   * Texto de um valor; vazio quando o valor não é escalar.
   */
  private function textValue(mixed $value): string {
    return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
  }

  /**
   * Inteiro de um valor; zero quando não é numérico.
   */
  private function intValue(mixed $value): int {
    return is_numeric($value) ? (int) $value : 0;
  }

}

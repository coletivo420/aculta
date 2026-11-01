<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;

/**
 * Envios recentes do Studio, por integração e por dono.
 *
 * Cada envio fica em uma subpasta da integração. O próprio usuário vê e usa os
 * seus envios. Administradores veem todos, para revisão e limpeza. O dono é
 * checado aqui, não na integração.
 */
final class RecentUploads {

  /**
   * Diretório privado dos envios do Studio. Cada integração tem uma subpasta.
   */
  public const STUDIO_DIRECTORY = 'private://studio/envio';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Envios recentes numa integração, do mais novo para o mais antigo.
   *
   * Sem $all, só os envios do usuário $uid. Com $all (administrador), todos.
   *
   * @return array<int, string>
   *   Mapa fid => nome do arquivo.
   */
  public function forUser(int $uid, string $integration, int $limit = 10, bool $all = FALSE): array {
    if (($uid < 1 && !$all) || !self::isIntegration($integration)) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('file');
    // accessCheck(FALSE): o filtro por dono é explícito (uid, ou $all do administrador).
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uri', self::directory($integration) . '/', 'STARTS_WITH');
    if (!$all) {
      $query->condition('uid', $uid);
    }
    $ids = $query
      ->sort('created', 'DESC')
      ->range(0, max(1, $limit))
      ->execute();

    $options = [];
    foreach ($storage->loadMultiple($ids) as $file) {
      if ($file instanceof FileInterface) {
        $options[(int) $file->id()] = $file->getFilename();
      }
    }
    return $options;
  }

  /**
   * Indica se o arquivo é um envio do usuário para a integração, ou se $all.
   *
   * $all é para administradores. A pasta da integração e a ausência de ".."
   * valem sempre.
   */
  public function isOwnedBy(FileInterface $file, int $uid, string $integration, bool $all = FALSE): bool {
    if (($uid < 1 && !$all) || !self::isIntegration($integration)) {
      return FALSE;
    }
    $uri = $file->getFileUri();
    if (!str_starts_with($uri, self::directory($integration) . '/') || str_contains($uri, '..')) {
      return FALSE;
    }
    return $all || (int) $file->getOwnerId() === $uid;
  }

  /**
   * Subpasta de uma integração dentro do diretório do Studio.
   */
  private static function directory(string $integration): string {
    return self::STUDIO_DIRECTORY . '/' . $integration;
  }

  /**
   * Nome de integração seguro para compor caminho: só letras minúsculas.
   */
  private static function isIntegration(string $integration): bool {
    return preg_match('/^[a-z]+$/', $integration) === 1;
  }

}

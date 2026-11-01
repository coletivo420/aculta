<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;

/**
 * Envios recentes do Studio, por integração e por dono.
 *
 * Cada envio fica em uma subpasta da integração. Só o próprio usuário enxerga
 * e pode usar os seus envios: o dono é checado aqui, não na integração.
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
   * Envios recentes do usuário numa integração, do mais novo para o mais antigo.
   *
   * @return array<int, string>
   *   Mapa fid => nome do arquivo.
   */
  public function forUser(int $uid, string $integration, int $limit = 10): array {
    if ($uid < 1 || !self::isIntegration($integration)) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('file');
    // accessCheck(FALSE): o filtro por dono é explícito, pela condição de uid.
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uri', self::directory($integration) . '/', 'STARTS_WITH')
      ->condition('uid', $uid)
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
   * Indica se o arquivo é um envio do próprio usuário para a integração.
   */
  public function isOwnedBy(FileInterface $file, int $uid, string $integration): bool {
    if ($uid < 1 || !self::isIntegration($integration)) {
      return FALSE;
    }
    $uri = $file->getFileUri();
    return (int) $file->getOwnerId() === $uid
      && str_starts_with($uri, self::directory($integration) . '/')
      && !str_contains($uri, '..');
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

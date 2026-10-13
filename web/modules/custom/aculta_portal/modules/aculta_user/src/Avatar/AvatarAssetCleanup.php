<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Avatar;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Limpeza de assets temporários de avatar (chamada pelo cron do aculta_user).
 *
 * Remove:
 * 1. uploads temporários de foto (status 0) em pictures/ ou temporary://, com mais de uma hora, que não foram salvos;
 * 2. cópias do acervo (public://pictures/acervo/) que nenhuma conta usa mais (troca de avatar, remoção de foto).
 *
 * Nunca remove arquivo em uso por alguma conta, nem foto enviada pela pessoa que ainda está no perfil.
 */
final class AvatarAssetCleanup {

  /** Idade mínima de um upload temporário para ser apagado (segundos). */
  public const TEMP_MAX_AGE = 3600;

  /** Idade mínima de uma foto personalizada sem uso para ser apagada (segundos). */
  public const PERSONAL_MIN_AGE = 86400;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly FileUsageInterface $fileUsage,
  ) {}

  /** Executa a limpeza. Retorna a quantidade de arquivos removidos por tipo. */
  public function run(): array {
    $fileStorage = $this->entityTypeManager->getStorage('file');
    $now = (int) $this->time->getRequestTime();
    $removed = ['temporarios' => 0, 'acervo_orfao' => 0, 'pessoais_orfas' => 0, 'recortes_orfaos' => 0];

    $temp = $fileStorage->getQuery()->accessCheck(FALSE)
      ->condition('status', 0)
      ->condition('created', $now - self::TEMP_MAX_AGE, '<')
      ->execute();
    foreach ($fileStorage->loadMultiple($temp) as $file) {
      if ($this->isPictureUri($file->getFileUri())) {
        $file->delete();
        $removed['temporarios']++;
      }
    }

    $acervo = $fileStorage->getQuery()->accessCheck(FALSE)
      ->condition('uri', 'public://pictures/acervo/%', 'LIKE')
      ->execute();
    foreach ($fileStorage->loadMultiple($acervo) as $file) {
      if (!$this->isInUse((int) $file->id())) {
        $file->delete();
        $removed['acervo_orfao']++;
      }
    }
    // Foto personalizada sem conta e sem registro de uso (ex.: recorte ou upload abandonado depois de salvo).
    $personal = $fileStorage->getQuery()->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('uri', 'public://pictures/%', 'LIKE')
      ->condition('uri', 'public://pictures/acervo/%', 'NOT LIKE')
      ->condition('changed', $now - self::PERSONAL_MIN_AGE, '<')
      ->execute();
    foreach ($fileStorage->loadMultiple($personal) as $file) {
      if (!$this->isInUse((int) $file->id()) && $this->fileUsage->listUsage($file) === []) {
        $file->delete();
        $removed['pessoais_orfas']++;
      }
    }

    // Recortes de arquivos que já não existem mais.
    $cropStorage = $this->entityTypeManager->getStorage('crop');
    foreach ($cropStorage->loadMultiple() as $crop) {
      $uri = $crop->hasField('uri') ? (string) $crop->get('uri')->value : '';
      if ($uri !== '' && str_starts_with($uri, 'public://pictures/') && !$this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $uri])) {
        $crop->delete();
        $removed['recortes_orfaos']++;
      }
    }
    return $removed;
  }

  private function isPictureUri(string $uri): bool {
    return str_starts_with($uri, 'public://pictures/') || str_starts_with($uri, 'temporary://');
  }

  /** Verdadeiro se alguma conta ainda referencia o arquivo como foto. */
  private function isInUse(int $fid): bool {
    return $this->entityTypeManager->getStorage('user')->getQuery()->accessCheck(FALSE)
      ->condition('user_picture.target_id', $fid)
      ->range(0, 1)
      ->count()
      ->execute() > 0;
  }

}

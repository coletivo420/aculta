<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\file\FileRepositoryInterface;
use Drupal\aculta_wiki\Upload\WikiLeadImageUploader;

/**
 * Keeps private lead image downloads aligned with current published entries.
 */
final class WikiLeadImageDownloadAccess {

  public function __construct(
    private readonly FileRepositoryInterface $fileRepository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Denies a historical private-file reference after its current use ends.
   *
   * Core permits a private file download when any revision references it,
   * including a previously published revision. Wiki lead images follow the
   * current published Node reference instead. The file remains stored for old
   * revisions while current published entries can continue displaying it.
   *
   * @param string $uri
   *   File URI being downloaded.
   *
   * @return int|null
   *   -1 to deny a Wiki lead image; NULL for Core and other modules to decide.
   */
  #[Hook('file_download')]
  public function fileDownload(string $uri): ?int {
    if (!str_starts_with($uri, WikiLeadImageUploader::DIRECTORY . '/')) {
      return NULL;
    }

    try {
      $file = $this->fileRepository->loadByUri($uri);
      if ($file === NULL) {
        return -1;
      }

      $mediaIds = $this->entityTypeManager->getStorage('media')->getQuery()
        ->accessCheck(FALSE)
        ->condition('bundle', 'image')
        ->condition('status', 1)
        ->condition('field_media_image.target_id', $file->id())
        ->execute();
      if ($mediaIds === []) {
        // Mídia não publicada (verbete em rascunho): só quem pode editar o verbete vê a prévia.
        return $this->editableDraftAccess($file->id());
      }

      $publishedReferences = $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'wiki_entry')
        ->condition('status', 1)
        ->condition(WikiLeadImageUploader::FIELD . '.target_id', array_values($mediaIds), 'IN')
        ->range(0, 1)
        ->execute();

      if ($publishedReferences !== []) {
        return NULL;
      }
      return $this->editableDraftAccess($file->id());
    }
    catch (\Throwable) {
      // Incomplete entity configuration or a lookup failure must not expose a
      // file from the Wiki's private upload directory.
      return -1;
    }
  }


  /**
   * Libera a prévia só a quem pode editar um verbete (qualquer status) que usa o arquivo.
   */
  private function editableDraftAccess(int $fileId): ?int {
    $mediaIds = $this->entityTypeManager->getStorage('media')->getQuery()
      ->accessCheck(FALSE)
      ->condition('bundle', 'image')
      ->condition('field_media_image.target_id', $fileId)
      ->execute();
    if ($mediaIds === []) {
      return -1;
    }
    $nodeIds = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'wiki_entry')
      ->condition(WikiLeadImageUploader::FIELD . '.target_id', array_values($mediaIds), 'IN')
      ->range(0, 20)
      ->execute();
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nodeIds) as $node) {
      if ($node->access('update', $this->currentUser)) {
        return NULL;
      }
    }
    return -1;
  }
}

<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\aculta_lms\Entity\AnswerEntity;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streams a private ACULTA answer attachment after route access is granted. */
final class AcultaAnswerFileDownloadController {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Returns the answer's private file as an attachment.
   */
  public function download(AnswerEntity $answer): BinaryFileResponse {
    $fileId = $answer->getAnswerPayload()->fileId();
    if ($fileId === NULL) {
      throw new NotFoundHttpException();
    }
    $file = $this->entityTypeManager->getStorage('file')->load($fileId);
    if (!$file instanceof FileInterface || $file->isTemporary()
      || !str_starts_with($file->getFileUri(), 'private://')) {
      throw new NotFoundHttpException();
    }
    $path = $this->fileSystem->realpath($file->getFileUri());
    if (!is_string($path) || $path === '') {
      throw new NotFoundHttpException();
    }

    $response = new BinaryFileResponse($path);
    $response->headers->set('Content-Type', 'application/octet-stream');
    $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
      HeaderUtils::DISPOSITION_ATTACHMENT,
      $file->getFilename(),
    ));
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
  }

}

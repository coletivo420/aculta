<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\aculta_lms\Plugin\ActivityAnswer\FileUpload;
use Drupal\Core\Controller\ControllerBase;
use Drupal\file\FileInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\TrainingManager;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Streams the file of a file upload answer (ported from lms_file_upload).
 */
final class AnswerFileDownloadController extends ControllerBase {

  public function __construct(
    protected readonly TrainingManager $trainingManager,
  ) {}

  /**
   * Streams the uploaded file for a given answer.
   */
  public function download(AnswerInterface $lms_answer): StreamedResponse {
    $plugin = $this->trainingManager->getActivityAnswerPlugin($lms_answer->getActivity());
    if (!$plugin instanceof FileUpload) {
      throw new AccessDeniedHttpException();
    }

    $file = $plugin->getFile($lms_answer);
    if (!$file instanceof FileInterface) {
      throw new AccessDeniedHttpException();
    }

    $uri = $file->getFileUri();
    $filename = $file->getFilename();
    $mime = $file->getMimeType();

    $response = new StreamedResponse(static function () use ($uri): void {
      $stream = \fopen($uri, 'rb');
      if ($stream === FALSE) {
        return;
      }
      \fpassthru($stream);
      \fclose($stream);
    });

    $response->headers->set('Content-Type', $mime);
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

}

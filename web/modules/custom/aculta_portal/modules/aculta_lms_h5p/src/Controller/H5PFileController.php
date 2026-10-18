<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Controller;

use Drupal\aculta_lms_h5p\Access\H5PCourseAccessManager;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PStoragePathInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Serves H5P assets from private storage with explicit path and access checks. */
final class H5PFileController implements ContainerInjectionInterface {

  public function __construct(
    private readonly H5PCourseAccessManager $courseAccess,
    private readonly AccountInterface $currentUser,
    private readonly H5PStoragePathInterface $storagePath,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_lms_h5p.course_access'), $container->get('current_user'), $container->get('aculta_lms_h5p.storage_path'));
  }

  /** Serves a content asset only to members who can access its course. */
  public function contentFile(int $group_id, int $content_id, string $file_path): Response {
    if (!$this->courseAccess->studentAccess($group_id, $content_id, $this->currentUser)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    return $this->serve($this->storagePath->getPath() . '/content/' . $content_id, $file_path, FALSE);
  }

  /** Serves installed library runtime assets; package uploads are not exposed. */
  public function libraryFile(string $file_path): Response {
    return $this->serve($this->storagePath->getPath() . '/libraries', $file_path, TRUE);
  }

  private function serve(string $base, string $relative, bool $library): Response {
    if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '\\') || preg_match('@(^|/)\.\.?(/|$)@', $relative)) {
      throw new NotFoundHttpException();
    }
    $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
    $allowed = $library
      ? ['js', 'css', 'json', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'woff', 'woff2', 'ttf', 'otf', 'mp3', 'mp4', 'webm', 'vtt']
      : ['json', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'woff', 'woff2', 'ttf', 'otf', 'mp3', 'mp4', 'webm', 'vtt', 'txt', 'pdf'];
    if (!in_array($extension, $allowed, TRUE)) {
      throw new NotFoundHttpException();
    }
    $root = realpath($base);
    $path = realpath($base . '/' . $relative);
    if ($root === FALSE || $path === FALSE || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path) || !is_readable($path)) {
      throw new NotFoundHttpException();
    }

    $mime = (new \Symfony\Component\Mime\MimeTypes())->getMimeTypes($extension)[0] ?? 'application/octet-stream';
    $response = new BinaryFileResponse($path);
    $response->headers->set('Content-Type', $mime);
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('Cache-Control', $library ? 'public, max-age=3600' : 'private, no-store');
    if ($extension === 'pdf') {
      $response->setContentDisposition('attachment', basename($path));
    }
    if ($extension === 'svg') {
      $response->headers->set('Content-Security-Policy', "sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:");
    }
    return $response;
  }

}

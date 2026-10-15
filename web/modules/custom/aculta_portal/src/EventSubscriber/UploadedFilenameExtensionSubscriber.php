<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Canonicalizes the extension case after Drupal's configured filename cleanup. */
final class UploadedFilenameExtensionSubscriber implements EventSubscriberInterface {

  /**
   * Runs after File module's configured sanitization and before Core's final security check.
   *
   * The non-tied priorities are part of the contract: File module uses the
   * default priority for configured cleanup and Core's security subscriber
   * uses PHP_INT_MIN to run last.
   *
   * @return array<class-string, array<int, array{0: string, 1?: int}>>
   *   Event subscriptions.
   */
  public static function getSubscribedEvents(): array {
    return [
      FileUploadSanitizeNameEvent::class => [['lowercaseExtension', -100]],
    ];
  }

  /** Lowercases only the final extension; Core still validates it afterwards. */
  public function lowercaseExtension(FileUploadSanitizeNameEvent $event): void {
    $filename = $event->getFilename();
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if ($extension === '') {
      return;
    }

    $event->setFilename(substr($filename, 0, -strlen($extension)) . strtolower($extension));
  }

}

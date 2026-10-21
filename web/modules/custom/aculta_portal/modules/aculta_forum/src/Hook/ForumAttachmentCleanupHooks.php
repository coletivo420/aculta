<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Attachment\ForumAttachmentCleanup;
use Drupal\aculta_forum\Attachment\ForumGalleryPublication;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs bounded cleanup of old Forum uploads.
 */
final class ForumAttachmentCleanupHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.attachment_cleanup')]
    private readonly ForumAttachmentCleanup $cleanup,
    #[Autowire(service: 'aculta_forum.gallery_publication')]
    private readonly ForumGalleryPublication $galleryPublication,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
  ) {}

  /**
   * Removes orphaned Forum uploads after the configured one-hour window.
   */
  #[Hook('cron')]
  public function cleanupForumAttachments(): void {
    $this->galleryPublication->cleanupOrphans($this->time->getRequestTime() - 3600);
    $this->cleanup->cleanup();
  }

}

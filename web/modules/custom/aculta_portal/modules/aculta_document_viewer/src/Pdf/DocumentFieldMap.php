<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Pdf;

/**
 * Single source of the Media bundle to document-field mapping.
 *
 * The builder and formatter must agree on which field carries the PDF.
 * This mapping keeps bundle support consistent across both classes.
 */
final class DocumentFieldMap {

  /**
   * Returns a Media bundle's document field, or NULL if it has no PDF viewer.
   *
   * A NULL bundle is refused like an unknown bundle.
   */
  public static function fieldForBundle(?string $bundle): ?string {
    return match ($bundle) {
      'document' => 'field_media_document',
      'course_document' => 'field_media_course_document',
      'forum_attachment' => 'field_media_forum_attachment',
      default => NULL,
    };
  }

}

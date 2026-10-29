<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer\Pdf;

/**
 * Single source of the Media bundle to document field mapping.
 *
 * The builder (access and markup) and formatter (field selection) must agree
 * on which field carries the PDF. Keeping the mapping here prevents a new
 * document bundle from being accepted in one place and refused in the other.
 */
final class DocumentFieldMap {

  /**
   * Returns the PDF field for a Media bundle, or NULL if unsupported.
   *
   * A NULL bundle is refused in the same way as an unknown bundle.
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

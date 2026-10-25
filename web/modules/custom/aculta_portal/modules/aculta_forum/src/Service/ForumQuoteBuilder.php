<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\Component\Utility\Html;

/**
 * Builds a safe, attributed blockquote from an already-rendered comment.
 */
final class ForumQuoteBuilder {

  private const MAX_LENGTH = 1200;

  /**
   * Returns editor-safe HTML, or NULL when the comment has no quoteable text.
   */
  public function build(string $processed, string $author): ?string {
    // Use the filtered output, discard markup and then escape the resulting
    // text again for the configured editor format. This prevents quoted input
    // from carrying embeds, attributes, or executable markup into a reply.
    $withBreaks = preg_replace('~</(?:p|div|li|blockquote|h[1-6])\s*>|<br\s*/?>~i', "\n", $processed) ?? $processed;
    $plain = Html::decodeEntities(strip_tags($withBreaks));
    $plain = preg_replace('/[\t ]+/u', ' ', trim($plain)) ?? trim($plain);
    $plain = preg_replace('/\n[\t ]*\n+/u', "\n", $plain) ?? $plain;
    $plain = mb_substr(trim($plain), 0, self::MAX_LENGTH);
    if ($plain === '') {
      return NULL;
    }

    $paragraphs = preg_split('/\R/u', $plain) ?: [$plain];
    $quoted = implode('', array_map(
      static fn (string $paragraph): string => '<p>' . Html::escape($paragraph) . '</p>',
      $paragraphs,
    ));
    $author = trim(preg_replace('/\s+/u', ' ', $author) ?? '');
    if ($author === '') {
      $author = 'Participante';
    }

    return '<blockquote>' . $quoted . '<cite>' . Html::escape($author) . '</cite></blockquote><p></p>';
  }

}

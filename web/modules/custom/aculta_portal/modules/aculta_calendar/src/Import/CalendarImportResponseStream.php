<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Import;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/**
 * Caps bytes written by the HTTP transport before they reach a response sink.
 */
final class CalendarImportResponseStream implements StreamInterface {

  use StreamDecoratorTrait;

  /**
   * Number of bytes already written to the wrapped stream. */
  private int $written = 0;

  /**
   * Whether a write attempt exceeded the configured byte limit. */
  private bool $limitExceeded = FALSE;

  public function __construct(
    private readonly StreamInterface $stream,
    private readonly int $maximumBytes,
  ) {}

  /**
   * Describes the write value.
   */
  public function write(string $string): int {
    $length = strlen($string);
    $remaining = max(0, $this->maximumBytes - $this->written);
    if ($length > $remaining) {
      if ($remaining > 0) {
        $this->written += $this->stream->write(substr($string, 0, $remaining));
      }
      $this->limitExceeded = TRUE;
      // A short cURL write aborts the transfer, bounding the sink itself.
      return 0;
    }

    $written = $this->stream->write($string);
    $this->written += $written;
    return $written;
  }

  /**
   * Describes the limitExceeded value.
   */
  public function limitExceeded(): bool {
    return $this->limitExceeded;
  }

}

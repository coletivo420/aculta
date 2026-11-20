<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\Component\Utility\Html;

/**
 * Stable user references inside Forum comment bodies.
 *
 * Mentions and quote attributions are stored as {uid:N} tokens, never as names.
 * Names are resolved at render time, so a renamed account updates every
 * existing mention and quote. Tokens are plain text, so editors keep them.
 */
final class ForumIdentityTokens {

  /**
   * Token body (without delimiters): {uid:N}, capturing N.
   */
  private const TOKEN_BODY = '\{uid:(\d+)\}';

  /**
   * Quoted block (without delimiters); mentions inside quotes are not tokenized.
   */
  private const QUOTE_BODY = '<blockquote\b[^>]*>.*?</blockquote>';

  /**
   * An @handle outside quotes (without delimiters), capturing the handle.
   */
  private const HANDLE_BODY = '(?<![\p{L}\p{N}_@.])@([\p{L}\p{N}_][\p{L}\p{N}_.-]{1,63})';

  /**
   * Token for an account reference.
   */
  public function token(int $uid): string {
    return '{uid:' . $uid . '}';
  }

  /**
   * Replaces @handles outside quotes with tokens, using the resolver.
   *
   * @param string $html
   *   Processed comment HTML.
   * @param callable(string): int $resolve
   *   Returns the active account id for a lowercase handle, or 0 when unknown.
   */
  public function tokenizeMentions(string $html, callable $resolve): string {
    $pieces = preg_split('~(' . self::QUOTE_BODY . ')~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($pieces === FALSE) {
      return $html;
    }
    $out = '';
    foreach ($pieces as $piece) {
      if (preg_match('~^<blockquote\b~i', $piece) === 1) {
        $out .= $piece;
        continue;
      }
      $out .= preg_replace_callback('/' . self::HANDLE_BODY . '/u', function (array $match) use ($resolve): string {
        $raw = $match[1];
        $handle = mb_strtolower(rtrim($raw, '.-'));
        $suffix = substr($raw, strlen(rtrim($raw, '.-')));
        $uid = mb_strlen($handle) >= 2 ? $resolve($handle) : 0;
        return $uid > 0 ? '@' . $this->token($uid) . $suffix : $match[0];
      }, $piece) ?? $piece;
    }
    return $out;
  }

  /**
   * Account ids referenced by mention tokens outside quotes, in order, unique.
   *
   * @param string $html
   *   Processed comment HTML.
   *
   * @return list<int>
   *   Positive account ids.
   */
  public function mentionedUids(string $html): array {
    $outside = preg_replace('~' . self::QUOTE_BODY . '~is', ' ', $html) ?? $html;
    if (preg_match_all('~@' . self::TOKEN_BODY . '~', $outside, $matches) === FALSE) {
      return [];
    }
    $ids = [];
    foreach ($matches[1] as $id) {
      $uid = (int) $id;
      if ($uid > 0 && !in_array($uid, $ids, TRUE)) {
        $ids[] = $uid;
      }
    }
    return $ids;
  }

  /**
   * Replaces every token with the current, escaped display name.
   *
   * @param string $html
   *   Processed comment HTML.
   * @param callable(int): ?string $nameOf
   *   Current display name for an account, or NULL when it no longer exists.
   */
  public function render(string $html, callable $nameOf): string {
    return preg_replace_callback('~' . self::TOKEN_BODY . '~', function (array $match) use ($nameOf): string {
      $name = $nameOf((int) $match[1]);
      return Html::escape($name ?? 'Usuário removido');
    }, $html) ?? $html;
  }

}

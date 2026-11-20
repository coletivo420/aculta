<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\ForumIdentityTokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Mentions are stored by account id and rendered with the current name.
 */
#[CoversClass(ForumIdentityTokens::class)]
#[Group('aculta_forum')]
final class ForumIdentityTokensTest extends TestCase {

  /**
   * Known handles become tokens; unknown ones stay as typed.
   */
  public function testKnownHandlesAreTokenized(): void {
    $tokens = new ForumIdentityTokens();
    $resolve = static fn(string $h): int => $h === 'maria' ? 12 : 0;
    $out = $tokens->tokenizeMentions('<p>Oi @Maria, fale com @ninguem.</p>', $resolve);
    self::assertSame('<p>Oi @{uid:12}, fale com @ninguem.</p>', $out);
  }

  /**
   * Mentions inside a quote are left untouched.
   */
  public function testQuotedMentionsAreNotTokenized(): void {
    $tokens = new ForumIdentityTokens();
    $html = '<blockquote data-aculta-quote-cid="9"><p>@maria</p></blockquote><p>@maria</p>';
    $out = $tokens->tokenizeMentions($html, static fn(): int => 12);
    self::assertSame('<blockquote data-aculta-quote-cid="9"><p>@maria</p></blockquote><p>@{uid:12}</p>', $out);
  }

  /**
   * Account ids come from tokens outside quotes, without duplicates.
   */
  public function testMentionedUidsReadTokensOutsideQuotes(): void {
    $tokens = new ForumIdentityTokens();
    $html = '<p>@{uid:12} e @{uid:7} e @{uid:12}</p><blockquote><p>@{uid:99}</p></blockquote>';
    self::assertSame([12, 7], $tokens->mentionedUids($html));
  }

  /**
   * Renaming an account changes the rendered text of every token.
   */
  public function testRenderUsesCurrentName(): void {
    $tokens = new ForumIdentityTokens();
    $names = [12 => 'Maria Nova'];
    $html = '<p>@{uid:12}</p><cite>{uid:12}</cite><cite>{uid:55}</cite>';
    $out = $tokens->render($html, static fn(int $uid): ?string => $names[$uid] ?? NULL);
    self::assertSame('<p>@Maria Nova</p><cite>Maria Nova</cite><cite>Usuário removido</cite>', $out);
  }

}

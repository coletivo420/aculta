<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Monitor\CurlImapClient;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Resposta `* SEARCH` do IMAP: CRLF do servidor, UIDs acima do cursor e sem resultado.
 */
#[Group('aculta_mailer')]
final class ImapSearchParserTest extends UnitTestCase {

  /**
   * Resposta real do servidor: linhas terminadas em CRLF.
   */
  public function testParsesCrlfResponse(): void {
    $response = "* SEARCH 1 2\r\na1 OK SEARCH completed\r\n";
    $this->assertSame([1, 2], CurlImapClient::parseSearch($response, 0));
  }

  /**
   * Só os UIDs acima do cursor entram, em ordem e sem repetição.
   */
  public function testFiltersByCursorAndSorts(): void {
    $response = "* SEARCH 7 3 5\r\n* SEARCH 5\r\na OK done\r\n";
    $this->assertSame([5, 7], CurlImapClient::parseSearch($response, 4));
  }

  /**
   * Resposta sem UIDs (caixa vazia) não gera nada.
   */
  public function testEmptyResultIsEmpty(): void {
    $this->assertSame([], CurlImapClient::parseSearch("* SEARCH\r\na OK done\r\n", 0));
    $this->assertSame([], CurlImapClient::parseSearch("a OK done\r\n", 0));
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_wiki\Hook\WikiEntryImageHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regressão: a prévia privada usa a forma de query ?file= (o caminho /system/files/{arquivo} dá 404).
 */
#[Group('aculta_wiki')]
#[CoversClass(WikiEntryImageHooks::class)]
final class WikiPreviewQueryPathTest extends UnitTestCase {

  /**
   * URI privada vira caminho relativo para a forma de query.
   */
  public function testPrivateUriBecomesQueryPath(): void {
    self::assertSame('wiki/imagens/valida_0.png', WikiEntryImageHooks::privateFileQueryPath('private://wiki/imagens/valida_0.png'));
  }

  /**
   * Arquivos públicos e vazios não usam a forma privada.
   */
  public function testNonPrivateUrisAreNotQueryPaths(): void {
    self::assertNull(WikiEntryImageHooks::privateFileQueryPath('public://galerias/uploads/valida.png'));
    self::assertNull(WikiEntryImageHooks::privateFileQueryPath('private://'));
  }

}

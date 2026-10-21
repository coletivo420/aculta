<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Unit\Document;

use Drupal\aculta_document_viewer\Document\DocumentViewerChrome;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cabeçalho e controles compartilhados escapam título e tipo.
 */
#[CoversClass(DocumentViewerChrome::class)]
#[Group('aculta_document_viewer')]
final class DocumentViewerChromeTest extends UnitTestCase {

  /**
   * Título com HTML sai escapado; a barra e o status existem com atributos neutros.
   */
  public function testTitleIsEscapedAndControlsAreNeutral(): void {
    $elements = DocumentViewerChrome::elements('<img src=x onerror=alert(1)> Relatório', new TranslatableMarkup('Planilha', [], [], $this->getStringTranslationStub()));

    $title = $elements['header']['title']['#markup'];
    $this->assertStringNotContainsString('<img', $title);
    $this->assertStringContainsString('&lt;img', $title);
    $this->assertSame('Planilha', (string) $elements['header']['type']['#markup']);
    $this->assertSame('toolbar', $elements['toolbar']['#attributes']['data-aculta-document-control']);
    $this->assertSame('status', $elements['status']['#attributes']['data-aculta-document-control']);
    $this->assertSame('polite', $elements['status']['#attributes']['aria-live']);
  }

}

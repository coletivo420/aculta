<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Scorm\ScormManifestParser;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the SCORM manifest reader for 1.2 and 2004 packages.
 */
#[CoversClass(ScormManifestParser::class)]
final class ScormManifestParserTest extends UnitTestCase {

  /**
   * Lê um manifesto SCORM 1.2 com uma organização e um item lançável.
   */
  public function testParsesScorm12Manifest(): void {
    $result = (new ScormManifestParser())->parse($this->manifest('1.2', 'sco', 'index.html'));

    $this->assertSame('1.2', $result['version']);
    $this->assertSame('ORG-1', $result['default_organization']);
    $this->assertSame(1, count($result['launch_items']));
    $this->assertSame('index.html', $result['launch_items'][0]['href']);
    $this->assertSame('Aula 1', $result['launch_items'][0]['title']);
    $this->assertSame('sco', $result['launch_items'][0]['type']);
  }

  /**
   * Lê um manifesto 2004 e identifica a versão pelo schemaversion.
   */
  public function testParsesScorm2004Manifest(): void {
    $result = (new ScormManifestParser())->parse($this->manifest('2004 4th Edition', 'sco', 'play/index.html'));

    $this->assertSame('2004', $result['version']);
    $this->assertSame('play/index.html', $result['launch_items'][0]['href']);
  }

  /**
   * DOCTYPE é recusado antes de qualquer leitura de entidade.
   */
  public function testRejectsDoctype(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('DOCTYPE');
    (new ScormManifestParser())->parse('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]>' . $this->manifest('1.2', 'sco', 'a.html'));
  }

  /**
   * Versão que não é 1.2 nem 2004 é recusada.
   */
  public function testRejectsUnknownVersion(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('Versão SCORM desconhecida');
    (new ScormManifestParser())->parse($this->manifest('0.9', 'sco', 'a.html'));
  }

  /**
   * Item que aponta para recurso inexistente invalida o manifesto.
   */
  public function testRejectsItemWithMissingResource(): void {
    $xml = str_replace('identifierref="RES-1"', 'identifierref="RES-OUTRO"', $this->manifest('1.2', 'sco', 'a.html'));
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('recurso inexistente');
    (new ScormManifestParser())->parse($xml);
  }

  /**
   * Manifesto sem nenhum item lançável é recusado.
   */
  public function testRejectsManifestWithoutLaunchItem(): void {
    $xml = str_replace('identifierref="RES-1"', '', $this->manifest('1.2', 'sco', 'a.html'));
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('item lançável');
    (new ScormManifestParser())->parse($xml);
  }

  /**
   * Monta um manifesto mínimo com uma organização, um item e um recurso.
   */
  private function manifest(string $schemaversion, string $scormType, string $href): string {
    return '<?xml version="1.0" encoding="UTF-8"?>'
      . '<manifest identifier="MAN-1" xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">'
      . '<metadata><schema>ADL SCORM</schema><schemaversion>' . $schemaversion . '</schemaversion></metadata>'
      . '<organizations default="ORG-1"><organization identifier="ORG-1"><title>Curso</title>'
      . '<item identifier="ITEM-1" identifierref="RES-1"><title>Aula 1</title></item>'
      . '</organization></organizations>'
      . '<resources><resource identifier="RES-1" type="webcontent" adlcp:scormtype="' . $scormType . '" href="' . $href . '">'
      . '<file href="' . $href . '"/></resource></resources>'
      . '</manifest>';
  }

}

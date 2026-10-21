<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_editor\Kernel;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifica os formatos editoriais do ACULTA Editor.
 */
#[Group('aculta_editor')]
#[RunTestsInSeparateProcesses]
final class AcultaEditorFormatsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'text',
    'filter',
    'editor',
    'ckeditor5',
    'media',
    'media_library',
    'views',
    'ckeditor5_plugin_pack',
    'ckeditor5_plugin_pack_templates',
    'aculta_editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'filter', 'editor', 'ckeditor5', 'ckeditor5_plugin_pack_templates', 'aculta_editor']);
  }

  /**
   * Formatos do submódulo.
   */
  private const FORMATS = [
    'aculta_basico',
    'aculta_editorial',
    'aculta_institucional',
    'aculta_email',
  ];

  /**
   * Cada formato tem filtro HTML e editor CKEditor 5 próprios.
   */
  public function testFormatsAndEditorsExist(): void {
    foreach (self::FORMATS as $id) {
      $format = FilterFormat::load($id);
      $this->assertNotNull($format, "Formato $id existe.");
      $this->assertTrue($format->filters('filter_html')->status, "$id usa filter_html.");
      $editor = Editor::load($id);
      $this->assertNotNull($editor, "Editor de $id existe.");
      $this->assertSame('ckeditor5', $editor->getEditor(), "$id usa CKEditor 5.");
    }
  }

  /**
   * Somente o formato básico é concedido ao editor de conteúdo.
   */
  public function testBasicFormatIsGrantedOnlyToContentEditor(): void {
    Role::create(['id' => 'content_editor', 'label' => 'Editor de conteúdo'])->save();
    $role = Role::load('content_editor');
    $role->grantPermission('use text format aculta_basico');
    $role->save();

    $this->assertTrue($role->hasPermission('use text format aculta_basico'));
    $this->assertFalse($role->hasPermission('use text format aculta_editorial'));
    $this->assertFalse($role->hasPermission('use text format aculta_institucional'));
    $this->assertFalse($role->hasPermission('use text format aculta_email'));
  }

  /**
   * Scripts, manipuladores de evento e URLs perigosas saem em todos os perfis.
   */
  public function testDangerousMarkupIsRemoved(): void {
    $payload = '<script>alert(1)</script><p onclick="x()">texto</p>'
      . '<a href="javascript:alert(1)">link</a><iframe src="https://example.com"></iframe>';
    foreach (self::FORMATS as $id) {
      $output = $this->filter($id, $payload);
      $this->assertStringNotContainsString('<script', $output, "$id remove script.");
      $this->assertStringNotContainsString('onclick', $output, "$id remove onclick.");
      $this->assertStringNotContainsString('javascript:', $output, "$id remove javascript:.");
      $this->assertStringNotContainsString('<iframe', $output, "$id remove iframe.");
    }
  }

  /**
   * O perfil editorial preserva tabelas e âncoras; o básico não as aceita.
   */
  public function testEditorialKeepsTablesAndAnchorsButBasicDoesNot(): void {
    $html = '<table><tr><td>1</td></tr></table><h2 id="secao">Seção</h2>';
    $editorial = $this->filter('aculta_editorial', $html);
    $this->assertStringContainsString('<table>', $editorial);
    $this->assertStringContainsString('id="secao"', $editorial);

    $basic = $this->filter('aculta_basico', $html);
    $this->assertStringNotContainsString('<table', $basic);
  }

  /**
   * O e-mail mantém atributos de layout de tabela e remove estilo inline.
   */
  public function testEmailKeepsTableLayoutAndDropsStyle(): void {
    $html = '<table width="600" cellpadding="0" style="color:red"><tr><td bgcolor="#fbf4e8">Texto</td></tr></table>';
    $output = $this->filter('aculta_email', $html);
    $this->assertStringContainsString('width="600"', $output);
    $this->assertStringContainsString('bgcolor="#fbf4e8"', $output);
    $this->assertStringNotContainsString('style=', $output);
  }

  /**
   * Os oito modelos existem e só usam formatos que os aceitam.
   */
  public function testTemplatesAreInstalledWithAllowedFormats(): void {
    $templates = $this->container->get('entity_type.manager')
      ->getStorage('ckeditor5_template')
      ->loadMultiple();
    $this->assertCount(8, $templates);
    foreach ($templates as $template) {
      $values = $template->toArray();
      $this->assertNotEmpty($values['data'], $template->id() . ' tem HTML.');
      $this->assertStringNotContainsString('<script', (string) $values['data']);
      foreach ($values['textFormats'] as $format) {
        $this->assertContains($format, ['aculta_editorial', 'aculta_institucional']);
      }
    }
  }

  /**
   * Processa texto com o filtro HTML do formato informado.
   */
  private function filter(string $format_id, string $text): string {
    $format = FilterFormat::load($format_id);
    $result = $format->filters()->get('filter_html')->process($text, 'pt-br');
    return $result->getProcessedText();
  }

}

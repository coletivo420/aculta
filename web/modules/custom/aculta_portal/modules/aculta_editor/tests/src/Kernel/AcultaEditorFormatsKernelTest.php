<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_editor\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Formatos unificados do Portal: funções, editores, sanitização e templates.
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
    'node',
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
   * Formatos que o site mantém: o nome de máquina é o do formato existente.
   */
  private const FORMATS = [
    'plain_text',
    'usuario_aculta',
    'revista_editorial',
    'email_html',
    'wiki',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'filter', 'editor', 'ckeditor5', 'ckeditor5_plugin_pack_templates', 'aculta_editor', 'node']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('node_type');
    // Os formatos do site vêm de config/sync; a Kernel cria só o filtro de HTML de cada um.
    FilterFormat::create(['format' => 'revista_editorial', 'name' => 'Aculta Editorial (HTML completo)'])->save();
    $this->createFilterFormat('usuario_aculta', 'Usuário ACULTA (HTML restrito)');
    $this->createFilterFormat('wiki', 'Aculta Wiki (somente Wiki)');
    $this->createFilterFormat('email_html', 'Aculta Email (e-mails)');
  }

  /**
   * Cria um formato com os filtros e o HTML permitido declarados em config/sync.
   */
  private function createFilterFormat(string $id, string $label): void {
    $file = dirname(__DIR__, 9) . '/config/sync/filter.format.' . $id . '.yml';
    $data = \Symfony\Component\Yaml\Yaml::parseFile($file);
    $filters = [];
    foreach (['filter_html', 'filter_htmlcorrector', 'media_embed'] as $name) {
      if (isset($data['filters'][$name])) {
        $filters[$name] = $data['filters'][$name];
      }
    }
    FilterFormat::create(['format' => $id, 'name' => $label, 'filters' => $filters])->save();
  }

  /**
   * Cada formato mantido existe.
   */
  public function testKeptFormatsExist(): void {
    foreach (self::FORMATS as $id) {
      $this->assertNotNull(FilterFormat::load($id), "Formato $id existe.");
    }
  }

  /**
   * Os formatos excedentes saíram da configuração de instalação.
   */
  public function testExcessFormatsAreNotInstalled(): void {
    foreach (['aculta_basico', 'aculta_editorial', 'aculta_institucional', 'aculta_email', 'restricted_html'] as $id) {
      $this->assertNull(FilterFormat::load($id), "$id foi removido.");
    }
  }

  /**
   * Usuário ACULTA, Wiki e e-mail: script, evento e URL perigosa saem.
   */
  public function testRestrictedFormatsRemoveDangerousMarkup(): void {
    $payload = '<script>alert(1)</script><p onclick="x()">texto</p><a href="javascript:alert(1)">l</a><iframe src="https://example.com"></iframe>';
    foreach (['usuario_aculta', 'wiki', 'email_html'] as $id) {
      $output = $this->filter($id, $payload);
      $this->assertStringNotContainsString('<script', $output, "$id remove script.");
      $this->assertStringNotContainsString('onclick', $output, "$id remove onclick.");
      $this->assertStringNotContainsString('javascript:', $output, "$id remove javascript:.");
      $this->assertStringNotContainsString('<iframe', $output, "$id remove iframe.");
    }
  }

  /**
   * O Editorial é HTML completo por decisão do responsável: não tem filtro de HTML.
   */
  public function testEditorialIsFullHtmlByDecision(): void {
    $this->assertFalse(FilterFormat::load('revista_editorial')->filters('filter_html')->status);
  }

  /**
   * E-mail: estilo inline sai; layout de tabela fica.
   */
  public function testEmailKeepsTableLayoutAndDropsStyle(): void {
    $html = '<table width="600" cellpadding="0" style="color:red"><tr><td bgcolor="#fbf4e8">Texto</td></tr></table>';
    $output = $this->filter('email_html', $html);
    $this->assertStringContainsString('width="600"', $output);
    $this->assertStringContainsString('bgcolor="#fbf4e8"', $output);
    $this->assertStringNotContainsString('style=', $output);
  }

  /**
   * O conteúdo do Editorial salvo volta do banco e renderiza.
   */
  public function testContentCycleSavesAndRendersEditorialBody(): void {
    NodeType::create(['type' => 'article', 'name' => 'Artigo'])->save();
    FieldStorageConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'type' => 'text_with_summary'])->save();
    FieldConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'bundle' => 'article', 'label' => 'Corpo'])->save();
    EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'article', 'mode' => 'full', 'status' => TRUE])
      ->setComponent('body', ['type' => 'text_default', 'label' => 'hidden'])
      ->save();

    $node = Node::create([
      'type' => 'article',
      'title' => 'Teste de ciclo',
      'body' => ['value' => '<h2 id="secao">Seção</h2><p>Texto</p>', 'format' => 'revista_editorial'],
    ]);
    $node->save();

    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $reloaded = Node::load($node->id());
    $this->assertSame('revista_editorial', $reloaded->get('body')->format);

    $build = $this->container->get('entity_type.manager')->getViewBuilder('node')->view($reloaded, 'full');
    $rendered = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('id="secao"', $rendered);
    $this->assertStringContainsString('Texto', $rendered);
  }

  /**
   * Os modelos de editorial usam o formato unificado.
   */
  public function testTemplatesUseKeptFormats(): void {
    $templates = $this->container->get('entity_type.manager')->getStorage('ckeditor5_template')->loadMultiple();
    $this->assertCount(8, $templates);
    foreach ($templates as $template) {
      foreach ($template->toArray()['textFormats'] as $format) {
        $this->assertContains($format, self::FORMATS);
      }
    }
  }

  /**
   * Processa texto com o filtro HTML do formato informado.
   */
  private function filter(string $format_id, string $text): string {
    $format = FilterFormat::load($format_id);
    return $format->filters()->get('filter_html')->process($text, 'pt-br')->getProcessedText();
  }

}

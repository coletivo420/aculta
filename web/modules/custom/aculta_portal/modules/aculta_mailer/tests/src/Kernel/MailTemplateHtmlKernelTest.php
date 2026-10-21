<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Entity\MailTemplate;
use Drupal\aculta_mailer\Hook\MailHooks;
use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Modelo HTML restrito: sanitização no render e escape dos valores do usuário.
 */
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailTemplateHtmlKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'filter', 'aculta_mailer', 'aculta_mailer_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['filter']);
    // Formato restrito equivalente ao aculta_email (o aculta_editor o cria em produção).
    FilterFormat::create([
      'format' => MailTemplate::BODY_FORMAT_HTML,
      'name' => 'Aculta Email (teste)',
      'filters' => [
        'filter_html' => [
          'id' => 'filter_html',
          'provider' => 'filter',
          'status' => TRUE,
          'weight' => -10,
          'settings' => ['allowed_html' => '<p> <strong> <a href>'],
        ],
      ],
    ])->save();
  }

  /**
   * Script e atributos de evento do modelo HTML não chegam ao e-mail.
   */
  public function testHtmlTemplateBodyIsSanitized(): void {
    $this->saveTemplate('<p>Olá {{ name }}</p><script>alert(1)</script><p onclick="x()">fim</p>');
    $rendered = $this->renderTemplate(['name' => 'Maria']);

    $this->assertTrue($rendered['html']);
    $this->assertStringContainsString('Olá Maria', $rendered['body']);
    $this->assertStringNotContainsString('<script', $rendered['body']);
    $this->assertStringNotContainsString('onclick', $rendered['body']);
  }

  /**
   * Valor do usuário com marcação vira texto escapado, não HTML.
   */
  public function testUserValueIsEscapedInHtmlTemplate(): void {
    $this->saveTemplate('<p>Olá {{ name }}</p>');
    $rendered = $this->renderTemplate(['name' => '<img src=x onerror=alert(1)>']);

    $this->assertStringContainsString('&lt;img', $rendered['body']);
    $this->assertStringNotContainsString('<img', $rendered['body']);
  }

  /**
   * O caminho de envio gera a parte HTML com o modelo sanitizado e o valor escapado.
   */
  public function testSendPathBuildsSanitizedHtmlPart(): void {
    $this->saveTemplate('<p>Olá {{ name }}</p><script>alert(1)</script>');
    $message = ['headers' => [], 'body' => [], 'subject' => ''];
    $this->container->get(MailHooks::class)->mail('aculta_mailer_test_message', $message, [
      'context' => ['name' => '<b>Maria</b>'],
    ]);

    $plugin = $this->container->get('plugin.manager.mail')->createInstance('aculta_mailer');
    $formatted = $plugin->format($message);
    $renderHtml = new \ReflectionMethod($plugin, 'renderHtml');
    $html = (string) $renderHtml->invoke($plugin, $formatted);

    $this->assertStringContainsString('Olá &lt;b&gt;Maria&lt;/b&gt;', $html);
    $this->assertStringNotContainsString('<script', $html);
    $this->assertStringNotContainsString('<b>Maria', $html);
  }

  /**
   * A prévia em HTML mostra o resultado sanitizado, sem script.
   */
  public function testHtmlPreviewIsSanitized(): void {
    $type = $this->container->get(MailTypeRegistry::class)->get('aculta_mailer_test_message');
    $preview = $this->container->get(MailTemplateRenderer::class)->preview($type, 'Assunto', '<p>Olá {{ name }}</p><script>alert(1)</script>', TRUE);
    $this->assertStringContainsString('<p>Olá ', $preview['body']);
    $this->assertStringNotContainsString('<script', $preview['body']);
  }

  /**
   * Modelo em texto simples continua sem filtro HTML e sem escape (regressão).
   */
  public function testPlainTemplateKeepsPreviousBehaviour(): void {
    $this->saveTemplate('Olá {{ name }}', FALSE);
    $rendered = $this->renderTemplate(['name' => 'Maria & <b>']);

    $this->assertFalse($rendered['html']);
    $this->assertSame('Olá Maria & <b>', $rendered['body']);
  }

  /**
   * Salva o modelo do tipo de teste com o corpo e o formato informados.
   */
  private function saveTemplate(string $body, bool $html = TRUE): void {
    MailTemplate::create([
      'id' => 'aculta_mailer_test_message',
      'label' => 'Teste HTML',
      'status' => TRUE,
      'subject' => 'Oi {{ name }}',
      'body' => $body,
      'body_format' => $html ? MailTemplate::BODY_FORMAT_HTML : '',
      'layout' => '',
    ])->save();
  }

  /**
   * Renderiza o tipo de teste com o contexto dado.
   *
   * @return array{subject: string, body: string, html: bool}
   */
  private function renderTemplate(array $context): array {
    $type = $this->container->get(MailTypeRegistry::class)->get('aculta_mailer_test_message');
    return $this->container->get(MailTemplateRenderer::class)->render($type, $context);
  }

}

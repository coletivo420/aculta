<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Entity\MailBrand;
use Drupal\aculta_mailer\Entity\MailLayout;
use Drupal\aculta_mailer\Entity\MailTemplate;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Modelos: modelo ativo sobrepõe o texto padrão, desativado não, e o editor recusa variável desconhecida.
 */
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailTemplateKernelTest extends KernelTestBase {

  /**
   * Módulos do teste.
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user', 'aculta_mailer', 'aculta_mailer_test'];

  /**
   * Correlation ID fixo usado nas mensagens de teste.
   */
  private const CORRELATION = '0190a3f2-7c1d-4e8a-9b2f-3d4e5f6a7b8c';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_mail_log');
  }

  /**
   * Modelo ativo: assunto e corpo vêm do modelo, com as variáveis preenchidas.
   */
  public function testActiveTemplateOverridesDefaultText(): void {
    $this->template(TRUE, 'Oi {{ name }}', 'Modelo {{ link }}');
    $this->send();

    $mail = $this->lastMail();
    $this->assertSame('Oi Maria', $mail['subject']);
    $this->assertStringContainsString('Modelo https://example.invalid/x', (string) $mail['body']);
  }

  /**
   * Modelo desativado: o texto padrão do tipo continua valendo.
   */
  public function testDisabledTemplateFallsBackToDefault(): void {
    $this->template(FALSE, 'Oi {{ name }}', 'Modelo {{ link }}');
    $this->send();

    $this->assertSame('Assunto de teste para Maria', $this->lastMail()['subject']);
  }

  /**
   * O editor recusa placeholder que o tipo não declara, no assunto e no corpo.
   */
  public function testFormRejectsUnknownPlaceholders(): void {
    $template = MailTemplate::create(['id' => 'aculta_mailer_test_message', 'label' => 'Teste', 'status' => TRUE]);
    $formObject = $this->container->get('entity_type.manager')->getFormObject('aculta_mailer_template', 'edit');
    $formObject->setEntity($template);
    $state = new FormState();
    $form = $formObject->buildForm([], $state);
    $state->setValues([
      'id' => 'aculta_mailer_test_message',
      'label' => 'Teste',
      'status' => TRUE,
      'subject' => 'Oi {{ senha }}',
      'body' => 'Corpo {{ name }}',
    ]);
    $formObject->validateForm($form, $state);

    $this->assertArrayHasKey('subject', $state->getErrors());
    $this->assertArrayNotHasKey('body', $state->getErrors());
  }

  /**
   * Modelo com layout: cabeçalho e rodapé da marca em torno do corpo, e o corpo não é reprocessado.
   */
  public function testLayoutWrapsBodyWithBrand(): void {
    MailBrand::create([
      'id' => 'aculta',
      'label' => 'ACULTA',
      'name' => 'ACULTA',
      'footer' => 'Associação Cultural',
    ])->save();
    MailLayout::create([
      'id' => 'transacional',
      'label' => 'Transacional',
      'brand' => 'aculta',
      'header' => '{{ brand_name }}',
      'footer' => '{{ brand_footer }}',
    ])->save();
    MailTemplate::create([
      'id' => 'aculta_mailer_test_message',
      'label' => 'Modelo com layout',
      'status' => TRUE,
      'layout' => 'transacional',
      'subject' => 'Oi {{ name }}',
      'body' => 'Corpo {{ name }} {{ brand_name }}',
    ])->save();
    $this->send();

    $body = (string) $this->lastMail()['body'];
    $this->assertStringStartsWith('ACULTA', $body);
    // `brand_name` não é variável do tipo: no corpo vira vazio, e nenhum placeholder cru vaza.
    $this->assertStringContainsString('Corpo Maria', $body);
    $this->assertStringNotContainsString('{{', $body);
    $this->assertStringEndsWith('Associação Cultural', trim($body));
  }

  /**
   * Cria o modelo do tipo de teste.
   */
  private function template(bool $status, string $subject, string $body): void {
    MailTemplate::create([
      'id' => 'aculta_mailer_test_message',
      'label' => 'Modelo de teste',
      'status' => $status,
      'subject' => $subject,
      'body' => $body,
    ])->save();
  }

  /**
   * Envia a mensagem de teste pela API.
   */
  private function send(): void {
    $context = ['name' => 'Maria', 'link' => 'https://example.invalid/x'];
    $message = new MailMessage('aculta_mailer_test_message', 'destino@example.invalid', 'pt-br', $context, self::CORRELATION);
    $this->assertTrue($this->container->get('aculta_mailer.mailer')->send($message));
  }

  /**
   * Último e-mail capturado pelo coletor de testes.
   *
   * @return array
   *   Campos do e-mail.
   */
  private function lastMail(): array {
    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    return end($mails);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Entity\MailBrand;
use Drupal\aculta_mailer\Entity\MailLayout;
use Drupal\aculta_mailer\Entity\MailTemplate;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\filter\Entity\FilterFormat;
use Drupal\aculta_mailer\Form\MailTemplateTestForm;
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
  protected static $modules = ['system', 'user', 'filter', 'aculta_mailer', 'aculta_mailer_test'];

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
    $this->installConfig(['filter']);
    // Formato do corpo HTML (o aculta_editor o cria em produção).
    FilterFormat::create(['format' => MailTemplate::BODY_FORMAT_HTML, 'name' => 'Aculta Email (teste)'])->save();
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
      'body' => ['value' => 'Corpo {{ name }}', 'format' => MailTemplate::BODY_FORMAT_HTML],
    ]);
    $formObject->validateForm($form, $state);

    $this->assertArrayHasKey('subject', $state->getErrors());
    $this->assertArrayNotHasKey('body', $state->getErrors());
  }

  /**
   * Modelo com layout: cabeçalho e rodapé da marca em torno do corpo, e o corpo não é reprocessado.
   */
  /**
   * Com corpo HTML, o texto de marca e de layout é escapado e as quebras viram <br>; o HTML do modelo não muda.
   */
  public function testLayoutWithHtmlBodyEscapesPlainLayoutText(): void {
    MailBrand::create([
      'id' => 'aculta',
      'label' => 'ACULTA',
      'name' => 'ACULTA & Amigos',
      'footer' => "Associação <Cultural>\nCNPJ 00",
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
      'label' => 'Modelo HTML com layout',
      'status' => TRUE,
      'layout' => 'transacional',
      'subject' => 'Oi {{ name }}',
      'body' => '<p><strong>Corpo</strong> {{ name }}</p>',
      'body_format' => MailTemplate::BODY_FORMAT_HTML,
    ])->save();
    $type = $this->container->get(MailTypeRegistry::class)->get('aculta_mailer_test_message');
    $rendered = $this->container->get(MailTemplateRenderer::class)->render($type, ['name' => 'Maria']);

    $this->assertStringContainsString('ACULTA &amp; Amigos', $rendered['body']);
    $this->assertStringContainsString('Associação &lt;Cultural&gt;<br>', $rendered['body']);
    $this->assertStringContainsString('CNPJ 00', $rendered['body']);
    $this->assertStringNotContainsString('<Cultural>', $rendered['body']);
    $this->assertStringContainsString('<p><strong>Corpo</strong> Maria</p>', $rendered['body']);
    // O envelope usa esse flag para não repetir o rodapé do tema quando a marca já o traz.
    $this->assertTrue($rendered['layout']);
  }

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
   * O envio de teste recusa endereço inválido e, com endereço válido, envia pelo caminho real com valores de exemplo.
   */
  public function testTestSendUsesSamplesAndRejectsBadAddress(): void {
    $template = MailTemplate::create([
      'id' => 'aculta_mailer_test_message',
      'label' => 'Modelo',
      'status' => TRUE,
      'subject' => 'Oi {{ name }}',
      'body' => 'Link {{ link }}',
    ]);
    $template->save();

    $form = MailTemplateTestForm::create($this->container);
    $form->setTemplate($template);
    $bad = new FormState();
    $built = $form->buildForm([], $bad);
    $bad->setValue('recipient', 'nao-e-email');
    $form->validateForm($built, $bad);
    $this->assertArrayHasKey('recipient', $bad->getErrors());

    $good = new FormState();
    $built = $form->buildForm([], $good);
    $good->setValue('recipient', 'destino@example.invalid');
    $form->validateForm($built, $good);
    $this->assertSame([], $good->getErrors());
    $form->submitForm($built, $good);

    $mail = $this->lastMail();
    $this->assertSame('destino@example.invalid', $mail['to']);
    $this->assertSame('Oi Maria', $mail['subject']);
    $this->assertStringContainsString('https://example.invalid/x', (string) $mail['body']);
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

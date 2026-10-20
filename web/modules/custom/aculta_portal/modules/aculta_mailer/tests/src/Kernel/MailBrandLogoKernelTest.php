<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Entity\MailBrand;
use Drupal\aculta_mailer\Entity\MailLayout;
use Drupal\aculta_mailer\Entity\MailTemplate;
use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Logo da marca (F4): o arquivo entra pela política do aculta_image e a URL chega ao envelope pelo layout ativo.
 */
#[CoversClass(MailTemplateRenderer::class)]
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailBrandLogoKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'aculta_image', 'aculta_mailer', 'aculta_mailer_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('aculta_mail_log');
    $this->installConfig(['system', 'file']);
  }

  /**
   * Marca com logo, ligada ao layout e ao modelo ativo, devolve a URL do arquivo.
   */
  public function testActiveBrandLogoIsReturnedForTheType(): void {
    $file = File::create(['uri' => 'public://aculta-mailer/marcas/logo.png', 'status' => 1]);
    $file->save();
    MailBrand::create([

      'id' => 'marca_teste',

      'label' => 'Marca',

      'name' => 'Marca',

      'logo_fid' => (int) $file->id(),

    ])->save();
    MailLayout::create([

      'id' => 'layout_teste',

      'label' => 'Layout',

      'header' => 'h',

      'footer' => 'f',

      'brand' => 'marca_teste',

    ])->save();
    MailTemplate::create([
      'id' => 'aculta_mailer_test_message',
      'label' => 'Modelo',
      'status' => TRUE,
      'subject' => 's',
      'body' => 'b',
      'layout' => 'layout_teste',
    ])->save();

    $renderer = $this->container->get(MailTemplateRenderer::class);
    $type = $this->container->get(MailTypeRegistry::class)->get('aculta_mailer_test_message');
    $url = $renderer->logoUrl($type);
    $this->assertStringContainsString('aculta-mailer/marcas/logo.png', $url);
  }

  /**
   * Sem logo na marca, a URL é vazia e o envelope usa o logo do tema.
   */
  public function testBrandWithoutLogoReturnsEmpty(): void {
    MailBrand::create([

      'id' => 'marca_sem_logo',

      'label' => 'Marca',

      'name' => 'Marca',

      'logo_fid' => 0,

    ])->save();
    MailLayout::create([

      'id' => 'layout_sem_logo',

      'label' => 'Layout',

      'header' => 'h',

      'footer' => 'f',

      'brand' => 'marca_sem_logo',

    ])->save();
    MailTemplate::create([
      'id' => 'aculta_mailer_test_message',
      'label' => 'Modelo',
      'status' => TRUE,
      'subject' => 's',
      'body' => 'b',
      'layout' => 'layout_sem_logo',
    ])->save();
    $type = $this->container->get(MailTypeRegistry::class)->get('aculta_mailer_test_message');
    $this->assertSame('', $this->container->get(MailTemplateRenderer::class)->logoUrl($type));
  }

}

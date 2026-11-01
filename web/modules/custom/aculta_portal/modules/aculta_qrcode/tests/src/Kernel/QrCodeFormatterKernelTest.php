<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_qrcode\Kernel;

use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\aculta_qrcode\Service\QrCodePublicUrlInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Formatter `aculta_qr_svg` em um campo de referência real para QR Code.
 */
#[Group('aculta_qrcode')]
#[RunTestsInSeparateProcesses]
final class QrCodeFormatterKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'aculta_qrcode',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user', 'field']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_qr_code');
    $this->container->set('aculta_qrcode.url_resolver', new class implements QrCodePublicUrlInterface {

      /**
       * URL fixa de teste para o código.
       */
      public function publicUrl(QrCode $entity): ?Url {
        return Url::fromUri('https://aculta.example/qr/' . $entity->getCode());
      }

    });
  }

  /**
   * O formatter mostra o SVG como imagem `data:`, sem markup inline.
   */
  public function testFormatterRendersSvgImageForReferencedCode(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_qr_teste',
      'entity_type' => 'user',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'aculta_qr_code'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_qr_teste',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Código QR',
    ])->save();

    $code = $this->container->get('aculta_qrcode.manager')->create('Cartaz', 'https://example.invalid/c');
    $account = User::create(['name' => 'portador-qr', 'status' => 1]);
    $account->set('field_qr_teste', $code->id());
    $account->save();

    $build = $account->get('field_qr_teste')->view(['type' => 'aculta_qr_svg']);
    $render = $this->render($build);

    self::assertStringContainsString('<img', $render);
    self::assertStringContainsString('src="data:image/svg+xml;base64,', $render);
    self::assertStringContainsString('alt="Código QR ' . $code->getCode() . '"', $render);
    self::assertStringNotContainsString('<svg', $render);
  }

}

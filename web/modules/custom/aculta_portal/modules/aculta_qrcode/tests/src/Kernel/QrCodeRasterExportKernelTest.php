<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_qrcode\Kernel;

use Drupal\aculta_image\GeneratedImageFileManager;
use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\aculta_qrcode\Service\QrCodeExporter;
use Drupal\aculta_qrcode\Service\QrCodePublicUrlInterface;
use Drupal\aculta_qrcode\Value\QrCodeFormat;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifica exportação raster com o registro temporário do aculta_image.
 */
#[Group('aculta_qrcode')]
#[RunTestsInSeparateProcesses]
final class QrCodeRasterExportKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'file',
    'image',
    'aculta_qrcode',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user', 'file', 'image']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('aculta_qr_code');
    $account = User::create(['name' => 'qr-admin', 'status' => 1]);
    $account->save();
    $this->container->get('current_user')->setAccount($account);
  }

  /**
   * Confirma que PNG raster passa pelo diretório e registro do aculta_image.
   */
  public function testPngRasterIsStoredAsTemporaryDerivative(): void {
    $manager = $this->container->get('aculta_qrcode.manager');
    $entity = $manager->create('Teste raster', 'https://example.invalid/qr');
    $urlResolver = new class implements QrCodePublicUrlInterface {

      /**
       * {@inheritdoc}
       */
      public function publicUrl(QrCode $entity): ?Url {
        return Url::fromUri('https://links.aculta.toca.net.br/qr/' . $entity->getCode());
      }

    };
    $generatedFiles = new GeneratedImageFileManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('file_system'),
      $this->container->get('file.mime_type.guesser'),
      new ImageInspector($this->container->get('image.factory')),
      $this->container->get('datetime.time'),
    );
    $exporter = new QrCodeExporter(
      $this->container->get('aculta_qrcode.generator'),
      $this->container->get('aculta_qrcode.preset_registry'),
      $urlResolver,
      $this->container->get('file_system'),
      $generatedFiles,
      NULL,
    );

    $bytes = $exporter->raster($entity, QrCodeFormat::Png);

    self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $bytes);
    $files = $this->container->get('entity_type.manager')->getStorage('file')->loadByProperties([
      'status' => 0,
    ]);
    self::assertCount(1, $files);
    self::assertStringStartsWith(QrCodeExporter::DIRECTORY . '/', reset($files)->getFileUri());
  }

}

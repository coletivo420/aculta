<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_file_uploader\FocalPointRecorder;
use Drupal\aculta_file_uploader\ImageDimensionReader;
use Drupal\image_widget_crop\ImageWidgetCropManager;
use Drupal\aculta_wiki\Upload\WikiLeadImageUploader;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regressão: o ponto focal do verbete é lido de wiki_lead_image.focal.* (caminho real do formulário).
 */
#[Group('aculta_wiki')]
#[CoversClass(WikiLeadImageUploader::class)]
final class WikiLeadImageFocalPathTest extends UnitTestCase {

  /** @var string[] */
  private array $expectedTypes = [];

  /** @var string[] */
  private array $expectedCalls = [];

  /**
   * Os valores do ponto focal chegam ao recorder pelo grupo 'focal' do formulário.
   */
  public function testFocalValuesAreReadFromFocalGroup(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn(785);
    $file->method('getFileUri')->willReturn('public://galerias/uploads/valida.png');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(785)->willReturn($file);
    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->with('file')->willReturn($storage);

    // Estrutura igual à do formulário: wiki_lead_image.focal.focal_x etc.
    $values = [
      'wiki_lead_image' => [
        'focal' => ['focal_fid' => '785', 'focal_x' => '66.63', 'focal_y' => '69.20'],
      ],
    ];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->willReturnCallback(
      fn(array $path, $default = NULL) => $this->valueAt($values, $path, $default),
    );

    $recorder = $this->realRecorder($etm, $file, ['aculta_ponto_focal_16x9', 'aculta_ponto_focal_1x1']);

    $uploader = (new \ReflectionClass(WikiLeadImageUploader::class))->newInstanceWithoutConstructor();
    $this->setProperty($uploader, 'entityTypeManager', $etm);
    $this->setProperty($uploader, 'focalPointRecorder', $recorder);

    $method = new \ReflectionMethod($uploader, 'recordFocal');
    $method->invoke($uploader, $form_state);
  }

  /**
   * Caminho antigo (valores soltos em wiki_lead_image) não grava nada.
   */
  public function testFlatFocalPathDoesNotRecord(): void {
    $values = ['wiki_lead_image' => ['focal_fid' => '785', 'focal_x' => '66.63', 'focal_y' => '69.20']];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->willReturnCallback(
      fn(array $path, $default = NULL) => $this->valueAt($values, $path, $default),
    );

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->expects(self::never())->method('getStorage');
    $recorder = $this->realRecorder($etm, NULL, []);

    $uploader = (new \ReflectionClass(WikiLeadImageUploader::class))->newInstanceWithoutConstructor();
    $this->setProperty($uploader, 'entityTypeManager', $etm);
    $this->setProperty($uploader, 'focalPointRecorder', $recorder);

    $method = new \ReflectionMethod($uploader, 'recordFocal');
    $method->invoke($uploader, $form_state);
  }

  /**
   * Recorder real com gerenciador de recorte mockado: o crop_type só é consultado se o record() recebeu valores.
   *
   * @param string[] $expected_types
   */
  private function realRecorder(EntityTypeManagerInterface $etm, ?FileInterface $file, array $expected_types): FocalPointRecorder {
    $cropTypes = $this->createMock(EntityStorageInterface::class);
    $loads = $expected_types === [] ? self::never() : self::exactly(count($expected_types));
    $calls = [];
    $cropTypes->expects($loads)->method('load')->willReturnCallback(function (string $id) use (&$calls) {
      $calls[] = $id;
      return NULL;
    });
    $etm_map = $this->createMock(EntityTypeManagerInterface::class);
    $etm_map->method('getStorage')->willReturnCallback(fn(string $type) => $type === 'crop_type' ? $cropTypes : $this->createMock(EntityStorageInterface::class));
    $manager = $this->createMock(ImageWidgetCropManager::class);
    $dims = $this->createMock(ImageDimensionReader::class);
    $recorder = new FocalPointRecorder($manager, $etm_map, $dims);
    $this->expectedCalls = &$calls;
    $this->expectedTypes = $expected_types;
    return $recorder;
  }

  /**
   * Confere os tipos de recorte consultados após o teste.
   */
  protected function tearDown(): void {
    if ($this->expectedTypes !== NULL && $this->expectedTypes !== []) {
      self::assertSame($this->expectedTypes, $this->expectedCalls, 'Os dois tipos de recorte devem ser consultados.');
    }
    parent::tearDown();
  }

  /**
   * Lê um caminho aninhado de um array de valores.
   */
  private function valueAt(array $values, array $path, mixed $default): mixed {
    foreach ($path as $key) {
      if (!is_array($values) || !array_key_exists($key, $values)) {
        return $default;
      }
      $values = $values[$key];
    }
    return $values;
  }

  /**
   * Define uma propriedade privada num objeto sem construtor.
   */
  private function setProperty(object $object, string $name, mixed $value): void {
    $prop = new \ReflectionProperty($object, $name);
    $prop->setValue($object, $value);
  }

}

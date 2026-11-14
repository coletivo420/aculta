<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

use Drupal\crop\Entity\Crop;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\image_widget_crop\ImageWidgetCropInterface;

/**
 * Grava o ponto focal de uma imagem como recortes na Crop API (16:9 e 1:1).
 *
 * Usa o mesmo caminho do recorte de avatar: applyCrop ou updateCrop do
 * image_widget_crop. A matemática do recorte fica em FocalPoint. O efeito nos
 * estilos de imagem é uma etapa separada.
 */
final class FocalPointRecorder {

  /**
   * Tipos de recorte do ponto focal e a proporção de cada um (largura / altura).
   */
  public const CROP_RATIOS = [
    'aculta_ponto_focal_16x9' => 16 / 9,
    'aculta_ponto_focal_1x1' => 1.0,
  ];

  public function __construct(
    private readonly ?ImageWidgetCropInterface $cropManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ImageDimensionReader $dimensionReader,
  ) {}

  /**
   * Grava os recortes do ponto focal. Retorna, por tipo, se foi gravado.
   *
   * @return array<string, bool>
   *   Indica, por tipo de recorte, se o registro foi gravado.
   */
  public function record(FileInterface $file, float $focalX, float $focalY): array {
    $result = [];
    if ($this->cropManager === NULL) {
      // Sem image_widget_crop o ponto focal não é gravado; falha fechada.
      return array_fill_keys(array_keys(self::CROP_RATIOS), FALSE);
    }
    $dimensions = $this->dimensionReader->dimensions($file->getFileUri());
    $cropTypes = $this->entityTypeManager->getStorage('crop_type');

    foreach (self::CROP_RATIOS as $typeId => $ratio) {
      $result[$typeId] = FALSE;
      $cropType = $cropTypes->load($typeId);
      if ($cropType === NULL || $dimensions === NULL) {
        continue;
      }

      $rect = FocalPoint::cropRectangle(
        $dimensions['width'],
        $dimensions['height'],
        FocalPoint::clampPercent($focalX),
        FocalPoint::clampPercent($focalY),
        $ratio,
      );
      $props = $rect + ['crop_applied' => '1'];
      $ref = ['file-id' => $file->id(), 'file-uri' => $file->getFileUri()];

      // updateCrop só atualiza crops ligados a um image style que usa o tipo.
      // Sem esse vínculo ele recria o crop, então a atualização vai direto na
      // entidade existente.
      $existing = Crop::findCrop($file->getFileUri(), $typeId);
      if ($existing instanceof Crop) {
        $this->cropManager->updateCropProperties($existing, $rect);
      }
      else {
        $this->cropManager->applyCrop($props, $ref, $cropType);
      }
      $result[$typeId] = TRUE;
    }

    return $result;
  }

}

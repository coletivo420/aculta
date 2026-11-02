<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\file\Element\ManagedFile;

/**
 * Upload com área de arrastar e soltar sobre o managed_file do Core.
 *
 * Estende o managed_file: o upload continua pelo File API do Core. Acrescenta
 * só a marcação da zona e a biblioteca de comportamento. Sem JavaScript, o
 * campo de arquivo comum continua funcionando.
 */
#[FormElement('aculta_upload_dropzone')]
class UploadDropZone extends ManagedFile {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $info = parent::getInfo();
    $info['#process'][] = [static::class, 'attachDropZone'];
    return $info;
  }

  /**
   * Marca o contêiner como zona de soltar e anexa a biblioteca.
   *
   * @param array<string, mixed> $element
   *   Elemento de formulário.
   *
   * @return array<string, mixed>
   *   Elemento com a zona e a biblioteca anexadas.
   */
  public static function attachDropZone(array $element): array {
    $element['#attributes']['class'][] = 'aculta-dropzone';
    $element['#attributes']['data-aculta-dropzone'] = TRUE;
    $element['#attached']['library'][] = 'aculta_file_uploader/upload-dropzone';
    return $element;
  }

}

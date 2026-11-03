<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader\Cleanup;

use Drupal\file\FileInterface;

/**
 * Contrato do dono da exclusão física de um File.
 *
 * O aculta_file_uploader confirma referências e pede a exclusão a quem é dono
 * do arquivo (hoje o aculta_image, por alias de serviço). Quem recusa mantém o
 * arquivo.
 */
interface FileDeletionOwnerInterface {

  /**
   * Remove o arquivo pela regra do dono. Retorna FALSE quando o dono recusa.
   */
  public function delete(FileInterface $file, string $reasonCode): bool;

}

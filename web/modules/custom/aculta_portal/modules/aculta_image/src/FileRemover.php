<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/**
 * Exclusão controlada de arquivo de imagem (aculta_image). Recusa apagar arquivo que ainda tem referência.
 *
 * A quarentena com janela de retenção (fase M7) substitui esta exclusão imediata. Por enquanto, quem chama já faz
 * a própria verificação de uso; este serviço confirma novamente no momento do delete.
 */
final class FileRemover {

  public function __construct(
    private readonly FileUsageInterface $fileUsage,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /** Apaga $file se nenhuma entidade o referencia. Retorna TRUE se foi apagado. */
  public function delete(FileInterface $file, string $motivo): bool {
    if (!empty($this->fileUsage->listUsage($file))) {
      $this->loggerFactory->get('aculta_image')->notice('Exclusão recusada: arquivo {fid} ainda em uso ({motivo}).', ['fid' => $file->id(), 'motivo' => $motivo]);
      return FALSE;
    }
    $this->entityTypeManager->getStorage('file')->delete([$file]);
    $this->loggerFactory->get('aculta_image')->info('Arquivo {fid} removido: {motivo}.', ['fid' => $file->id(), 'motivo' => $motivo]);
    return TRUE;
  }

}

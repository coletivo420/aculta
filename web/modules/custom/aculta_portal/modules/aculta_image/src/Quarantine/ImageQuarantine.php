<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Quarantine;

use Drupal\aculta_image\FileRemover;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/**
 * Quarentena de imagens candidatas (aculta_image, fase M7).
 *
 * Enviar move o arquivo para `private://aculta-image-quarentena` e registra a origem. Restaurar devolve ao local
 * original. Excluir só é possível após o prazo de retenção e depois de nova verificação de uso no momento da exclusão.
 * Arquivo com qualquer referência não sai do lugar.
 */
final class ImageQuarantine {

  /** Diretório privado da quarentena. */
  public const DIRETORIO = 'private://aculta-image-quarentena';

  public function __construct(
    private readonly Connection $database,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUsageInterface $fileUsage,
    private readonly FileRemover $remover,
    private readonly TimeInterface $time,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** Envia a imagem para a quarentena. Recusa se ela tiver qualquer referência. */
  public function enviar(FileInterface $arquivo, string $motivo): bool {
    if ($this->temReferencia($arquivo)) {
      return FALSE;
    }
    $agora = (int) $this->time->getRequestTime();
    $diretorio = self::DIRETORIO;
    $this->fileSystem->prepareDirectory($diretorio, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $original = $arquivo->getFileUri();
    $destino = self::DIRETORIO . '/' . $arquivo->id() . '-' . basename($original);
    $movido = $this->fileRepository->move($arquivo, $destino, FileExists::Rename);
    if ($movido === FALSE) {
      return FALSE;
    }
    $this->database->insert('aculta_image_quarantine')->fields([
      'fid' => (int) $arquivo->id(),
      'uri_original' => $original,
      'uri_quarentena' => $movido->getFileUri(),
      'motivo' => mb_substr($motivo, 0, 255),
      'enviado_em' => $agora,
      'vence_em' => QuarantineRules::vencimento($agora),
    ])->execute();
    return TRUE;
  }

  /** Devolve a imagem ao local original (com nome livre). */
  public function restaurar(int $fid): bool {
    $linha = $this->linha($fid);
    $arquivo = $this->arquivo($fid);
    if ($linha === NULL || $arquivo === NULL) {
      return FALSE;
    }
    $destino = $linha['uri_original'];
    $pasta = dirname($destino);
    $this->fileSystem->prepareDirectory($pasta, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    if ($this->fileRepository->move($arquivo, $destino, FileExists::Rename) === FALSE) {
      return FALSE;
    }
    $this->database->delete('aculta_image_quarantine')->condition('fid', $fid)->execute();
    return TRUE;
  }

  /** Entradas da quarentena, com o indicador de vencimento. */
  public function listar(): array {
    $agora = (int) $this->time->getRequestTime();
    $linhas = $this->database->select('aculta_image_quarantine', 'q')
      ->fields('q')
      ->orderBy('q.enviado_em', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($linhas as &$linha) {
      $linha['vencida'] = QuarantineRules::vencida((int) $linha['vence_em'], $agora);
    }
    return $linhas;
  }

  /**
   * Exclui as entradas vencidas que continuam sem referência. Retorna quantas foram apagadas.
   * Cada exclusão repete a verificação de uso; quem tiver referência permanece na quarentena.
   */
  public function excluirVencidas(): int {
    $apagadas = 0;
    foreach ($this->listar() as $linha) {
      if (!$linha['vencida']) {
        continue;
      }
      $fid = (int) $linha['fid'];
      $arquivo = $this->arquivo($fid);
      if ($arquivo === NULL) {
        $this->database->delete('aculta_image_quarantine')->condition('fid', $fid)->execute();
        continue;
      }
      if ($this->temReferencia($arquivo)) {
        continue;
      }
      if ($this->remover->delete($arquivo, 'quarentena vencida: ' . $linha['motivo'])) {
        $this->database->delete('aculta_image_quarantine')->condition('fid', $fid)->execute();
        $apagadas++;
      }
    }
    return $apagadas;
  }

  private function temReferencia(FileInterface $arquivo): bool {
    if (!empty($this->fileUsage->listUsage($arquivo))) {
      return TRUE;
    }
    return (bool) $this->database->select('entity_usage', 'eu')
      ->fields('eu', ['target_id'])
      ->condition('eu.target_type', 'file')
      ->condition('eu.target_id', (string) $arquivo->id())
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  private function linha(int $fid): ?array {
    $linha = $this->database->select('aculta_image_quarantine', 'q')->fields('q')->condition('q.fid', $fid)->execute()->fetchAssoc();
    return $linha === FALSE ? NULL : $linha;
  }

  private function arquivo(int $fid): ?FileInterface {
    $arquivo = $this->entityTypeManager->getStorage('file')->load($fid);
    return $arquivo instanceof FileInterface ? $arquivo : NULL;
  }

}

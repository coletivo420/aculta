<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Ingestão de imagem vinda de fora do site (aculta_image, regra B6 do plano).
 *
 * Única porta de entrada de arquivo de imagem de origem externa (importações, arquivos recebidos). Valida pela
 * política única, calcula SHA-256, reaproveita o arquivo quando o conteúdo já existe, nunca sobrescreve e registra a
 * origem (URL e data de captura) na tabela aculta_image_origin.
 */
final class ImageIngester {

  /** Diretório público das imagens importadas. */
  public const DIRETORIO = 'public://importacao';

  /** Resultados possíveis. */
  public const CRIADO = 'created';
  public const REAPROVEITADO = 'reused';
  public const RECUSADO = 'rejected';

  public function __construct(
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileSystemInterface $fileSystem,
    private readonly Connection $database,
    private readonly ImageInspector $inspector,
    private readonly TimeInterface $time,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /** Extensão aceita pela política única. */
  public static function extensaoAceita(string $nome): bool {
    $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
    return $ext !== '' && in_array($ext, explode(' ', UploadPolicy::EXTENSIONS), TRUE);
  }

  /** Tamanho dentro do limite da política única. */
  public static function tamanhoAceito(int $bytes): bool {
    return $bytes > 0 && $bytes <= UploadPolicy::MAX_BYTES;
  }

  /**
   * Ingere o arquivo de $caminho, com origem $urlOrigem capturada em $capturadoEm (timestamp).
   *
   * @return array{status: string, fid?: int, motivo?: string}
   */
  public function ingest(string $caminho, string $urlOrigem, int $capturadoEm): array {
    if (!is_file($caminho) || !is_readable($caminho)) {
      return $this->recusar('arquivo ausente ou ilegível', $urlOrigem);
    }
    $nome = basename($caminho);
    if (!self::extensaoAceita($nome)) {
      return $this->recusar('extensão fora da política', $urlOrigem);
    }
    $tamanho = (int) filesize($caminho);
    if (!self::tamanhoAceito($tamanho)) {
      return $this->recusar('tamanho fora da política', $urlOrigem);
    }
    $bytes = (string) file_get_contents($caminho);
    if ($this->inspector->fromBytes($bytes) === NULL) {
      return $this->recusar('conteúdo não é imagem válida', $urlOrigem);
    }

    $sha = hash('sha256', $bytes);
    $fid = $this->fidExistente($sha);
    if ($fid !== NULL) {
      $this->registrarOrigem($fid, $sha, $urlOrigem, $capturadoEm);
      return ['status' => self::REAPROVEITADO, 'fid' => $fid];
    }

    $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
    $destino = self::DIRETORIO . '/' . substr($sha, 0, 2) . '/' . $sha . '.' . $ext;
    $this->fileSystem->prepareDirectory(dirname($destino), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $arquivo = $this->fileRepository->writeData($bytes, $destino, FileExists::Rename);
    if ($arquivo === FALSE) {
      return $this->recusar('falha ao gravar o arquivo', $urlOrigem);
    }
    $arquivo->setPermanent();
    $arquivo->save();
    $this->registrarOrigem((int) $arquivo->id(), $sha, $urlOrigem, $capturadoEm);
    return ['status' => self::CRIADO, 'fid' => (int) $arquivo->id()];
  }

  /** FID de um arquivo ainda existente com o mesmo SHA-256, ou NULL. */
  private function fidExistente(string $sha): ?int {
    $fid = $this->database->select('aculta_image_origin', 'o')
      ->fields('o', ['fid'])
      ->condition('o.sha256', $sha)
      ->orderBy('o.id', 'ASC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($fid === FALSE) {
      return NULL;
    }
    $existe = $this->database->select('file_managed', 'f')
      ->fields('f', ['fid'])
      ->condition('f.fid', (int) $fid)
      ->execute()
      ->fetchField();
    return $existe === FALSE ? NULL : (int) $fid;
  }

  private function registrarOrigem(int $fid, string $sha, string $urlOrigem, int $capturadoEm): void {
    // Repetir a mesma importação não duplica a origem: uma linha por arquivo e URL de origem.
    $existe = $this->database->select('aculta_image_origin', 'o')
      ->fields('o', ['id'])
      ->condition('o.fid', $fid)
      ->condition('o.source_url', mb_substr($urlOrigem, 0, 2048))
      ->execute()
      ->fetchField();
    if ($existe !== FALSE) {
      return;
    }
    $this->database->insert('aculta_image_origin')
      ->fields([
        'fid' => $fid,
        'sha256' => $sha,
        'source_url' => mb_substr($urlOrigem, 0, 2048),
        'captured_at' => $capturadoEm,
        'imported_at' => (int) $this->time->getRequestTime(),
      ])
      ->execute();
  }

  /** @return array{status: string, motivo: string} */
  private function recusar(string $motivo, string $urlOrigem): array {
    $this->loggerFactory->get('aculta_image')->warning('Ingestão recusada ({motivo}): {url}', ['motivo' => $motivo, 'url' => $urlOrigem]);
    return ['status' => self::RECUSADO, 'motivo' => $motivo];
  }

}

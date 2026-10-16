<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Audit;

use Drupal\aculta_image\Visual\DHash;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;

/**
 * Inventário e auditoria de imagens (aculta_image, fases M0, M5 e M6 do plano). Somente leitura.
 *
 * Nunca apaga nem altera arquivo. Candidatas à revisão são sugestões com motivos; a exclusão só existe na
 * quarentena (fase M7), com confirmação administrativa.
 */
final class ImageAudit {

  /** Marca de imagem que o GD não lê. */
  public const SEM_LEITURA = 'ffffffffffffffff';

  /** Impressões de imagem sem gradiente (lisa ou quase lisa): não servem para comparar. */
  private const SEM_GRADIENTE = ['0000000000000000', 'ffffffffffffffff'];

  public function __construct(
    private readonly Connection $database,
    private readonly ?object $checksums = NULL,
    private readonly ?FileSystemInterface $fileSystem = NULL,
  ) {}

  /**
   * Resumo do acervo de imagens: quantidade, bytes, uso por file_usage e mídias.
   *
   * @return array{total: int, bytes: int, com_uso: int, sem_uso: int, midias: int}
   */
  public function resumo(): array {
    $total = (int) $this->database->select('file_managed', 'f')
      ->condition('f.filemime', 'image/%', 'LIKE')
      ->countQuery()->execute()->fetchField();
    $somaBytes = $this->database->select('file_managed', 'f');
    $somaBytes->condition('f.filemime', 'image/%', 'LIKE');
    $somaBytes->addExpression('COALESCE(SUM(f.filesize), 0)', 'bytes');
    $bytes = (int) $somaBytes->execute()->fetchField();
    $comUso = (int) $this->database->select('file_managed', 'f')
      ->condition('f.filemime', 'image/%', 'LIKE')
      ->condition('f.fid', $this->database->select('file_usage', 'fu')->fields('fu', ['fid']), 'IN')
      ->countQuery()->execute()->fetchField();
    $midias = (int) $this->database->select('media', 'm')->countQuery()->execute()->fetchField();
    return ['total' => $total, 'bytes' => $bytes, 'com_uso' => $comUso, 'sem_uso' => $total - $comUso, 'midias' => $midias];
  }

  /**
   * Grupos de mídias com a mesma checksum (media_duplicates). Vazio se o módulo não estiver disponível.
   *
   * @return array<int, array{checksum: string, midias: array<int|string, string>}>
   */
  public function gruposDeDuplicatas(): array {
    if ($this->checksums === NULL) {
      return [];
    }
    $grupos = [];
    foreach ($this->checksums->checksumsWithDuplicates() as $checksum => $quantidade) {
      $grupos[] = [
        'checksum' => (string) $checksum,
        'midias' => $this->checksums->checksumDuplicates($checksum),
      ];
    }
    return $grupos;
  }

  /**
   * Imagens sem nenhuma referência em file_usage: candidatas à revisão, com o motivo de cada uma.
   *
   * @return array<int, array{fid: int, uri: string, bytes: int, motivos: string[]}>
   */
  public function candidatas(int $limite = 200): array {
    $linhas = $this->database->select('file_managed', 'f')
      ->fields('f', ['fid', 'uri', 'filesize'])
      ->condition('f.filemime', 'image/%', 'LIKE')
      ->condition('f.fid', $this->database->select('file_usage', 'fu')->fields('fu', ['fid']), 'NOT IN')
      ->condition('f.fid', $this->database->select('aculta_image_quarantine', 'q')->fields('q', ['fid']), 'NOT IN')
      ->orderBy('f.fid', 'ASC')
      ->range(0, $limite)
      ->execute()
      ->fetchAll();
    $resultado = [];
    foreach ($linhas as $linha) {
      $resultado[] = [
        'fid' => (int) $linha->fid,
        'uri' => (string) $linha->uri,
        'bytes' => (int) $linha->filesize,
        'motivos' => self::motivos(FALSE, FALSE),
      ];
    }
    return $resultado;
  }

  /**
   * Calcula a impressão digital visual de até $limite imagens que ainda não têm uma (lote de cron).
   * Retorna quantas foram calculadas. Imagens que o GD não lê ficam sem impressão e não são tentadas de novo.
   */
  public function calcularImpressoes(int $limite = 200): int {
    if ($this->fileSystem === NULL) {
      return 0;
    }
    $linhas = $this->database->select('file_managed', 'f')
      ->fields('f', ['fid', 'uri'])
      ->condition('f.filemime', 'image/%', 'LIKE')
      ->condition('f.fid', $this->database->select('aculta_image_fingerprint', 'i')->fields('i', ['fid']), 'NOT IN')
      ->orderBy('f.fid', 'ASC')
      ->range(0, $limite)
      ->execute()
      ->fetchAll();
    $calculadas = 0;
    foreach ($linhas as $linha) {
      $caminho = $this->fileSystem->realpath((string) $linha->uri) ?: '';
      $impressao = DHash::deArquivo($caminho);
      if ($impressao === NULL) {
        $impressao = self::SEM_LEITURA;
      }
      $this->database->insert('aculta_image_fingerprint')->fields(['fid' => (int) $linha->fid, 'dhash' => $impressao])->execute();
      $calculadas++;
    }
    return $calculadas;
  }

  /**
   * Pares de imagens com impressão digital a no máximo $limiar bits de distância. Sugestão para revisão humana.
   *
   * @return array<int, array{a: int, b: int, distancia: int}>
   */
  public function paresVisuais(int $limiar = DHash::LIMIAR, int $limite = 200): array {
    $impressoes = array_filter($this->database->select('aculta_image_fingerprint', 'i')
      ->fields('i', ['fid', 'dhash'])
      ->range(0, $limite)
      ->execute()
      ->fetchAllKeyed(), static fn(string $dhash): bool => !in_array($dhash, self::SEM_GRADIENTE, TRUE));
    $fids = array_keys($impressoes);
    $pares = [];
    for ($i = 0; $i < count($fids); $i++) {
      for ($j = $i + 1; $j < count($fids); $j++) {
        $d = DHash::distancia((string) $impressoes[$fids[$i]], (string) $impressoes[$fids[$j]]);
        if ($d <= $limiar) {
          $pares[] = ['a' => (int) $fids[$i], 'b' => (int) $fids[$j], 'distancia' => $d];
        }
      }
    }
    usort($pares, static fn(array $x, array $y): int => $x['distancia'] <=> $y['distancia']);
    return $pares;
  }

  /**
   * Motivos de uma candidata. Pura: testável sem banco.
   *
   * @return string[]
   */
  public static function motivos(bool $usadaPorMidia, bool $usadaPorEntidade): array {
    $motivos = ['sem referência em file_usage'];
    if (!$usadaPorMidia) {
      $motivos[] = 'nenhuma mídia a referencia';
    }
    if (!$usadaPorEntidade) {
      $motivos[] = 'nenhuma entidade registrada em entity_usage';
    }
    return $motivos;
  }

}

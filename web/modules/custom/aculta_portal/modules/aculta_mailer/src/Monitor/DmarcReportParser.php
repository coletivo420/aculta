<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Monitor;

/**
 * Relatório agregado DMARC (RFC 7489, Apêndice C): anexo XML em texto, gzip ou zip, resumido em contagens.
 *
 * O XML é lido sem entidades externas. Só o resumo é guardado; o texto do relatório não é.
 */
final class DmarcReportParser {

  /** Tipos e nomes de anexo aceitos. */
  private const XML_TYPES = ['application/xml', 'text/xml'];

  private const ARCHIVE_TYPES = ['application/gzip', 'application/x-gzip', 'application/zip', 'application/x-zip-compressed'];

  /**
   * Resumo do primeiro relatório DMARC encontrado na mensagem, ou NULL.
   *
   * @return array{org: string, report_id: string, domain: string, begin: int, end: int, total: int, pass: int, fail: int, top_sources: array<string, int>}|null
   */
  public function parse(string $raw): ?array {
    foreach (MimeMessage::leaves($raw) as $leaf) {
      $xml = $this->xmlFromLeaf($leaf['type'], $leaf['filename'], $leaf['body']);
      if ($xml === NULL) {
        continue;
      }
      $summary = $this->summarize($xml);
      if ($summary !== NULL) {
        return $summary;
      }
    }
    return NULL;
  }

  /**
   * XML do anexo, conforme o tipo e o nome do arquivo (gzip e zip são descompactados).
   */
  private function xmlFromLeaf(string $type, string $filename, string $body): ?string {
    $name = strtolower($filename);
    if (in_array($type, self::XML_TYPES, TRUE) || str_ends_with($name, '.xml')) {
      return $body;
    }
    if (in_array($type, self::ARCHIVE_TYPES, TRUE) || str_ends_with($name, '.gz') || str_ends_with($name, '.zip')) {
      return str_ends_with($name, '.zip') || $type === 'application/zip' ? $this->fromZip($body) : $this->fromGzip($body);
    }
    return NULL;
  }

  private function fromGzip(string $body): ?string {
    $xml = @gzdecode($body);
    return $xml === FALSE ? NULL : $xml;
  }

  private function fromZip(string $body): ?string {
    $temp = tempnam(sys_get_temp_dir(), 'dmarc');
    if ($temp === FALSE) {
      return NULL;
    }
    try {
      file_put_contents($temp, $body);
      $zip = new \ZipArchive();
      if ($zip->open($temp) !== TRUE || $zip->numFiles < 1) {
        return NULL;
      }
      $xml = $zip->getFromIndex(0);
      $zip->close();
      return $xml === FALSE ? NULL : $xml;
    }
    finally {
      @unlink($temp);
    }
  }

  /**
   * Resume o XML do relatório, ou NULL se não for um relatório DMARC.
   *
   * @return array{org: string, report_id: string, domain: string, begin: int, end: int, total: int, pass: int, fail: int, top_sources: array<string, int>}|null
   */
  private function summarize(string $xml): ?array {
    $previous = libxml_use_internal_errors(TRUE);
    $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
    libxml_use_internal_errors($previous);
    if ($doc === FALSE || $doc->getName() !== 'feedback') {
      return NULL;
    }
    $total = 0;
    $pass = 0;
    $fail = 0;
    $sources = [];
    foreach ($doc->record ?? [] as $record) {
      $count = (int) $record->row->count;
      $evaluated = $record->row->policy_evaluated;
      $aligned = (string) $evaluated->dkim === 'pass' || (string) $evaluated->spf === 'pass';
      $total += $count;
      if ($aligned) {
        $pass += $count;
      }
      else {
        $fail += $count;
      }
      $ip = (string) $record->row->source_ip;
      $sources[$ip] = ($sources[$ip] ?? 0) + $count;
    }
    arsort($sources);
    return [
      'org' => (string) $doc->report_metadata->org_name,
      'report_id' => (string) $doc->report_metadata->report_id,
      'domain' => (string) $doc->policy_published->domain,
      'begin' => (int) $doc->report_metadata->date_range->begin,
      'end' => (int) $doc->report_metadata->date_range->end,
      'total' => $total,
      'pass' => $pass,
      'fail' => $fail,
      'top_sources' => array_slice($sources, 0, 5, TRUE),
    ];
  }

}

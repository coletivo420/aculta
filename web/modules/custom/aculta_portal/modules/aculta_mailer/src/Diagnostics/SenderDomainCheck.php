<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Diagnostics;

/**
 * Registros DNS de autenticação do domínio remetente: SPF, DKIM e DMARC (TXT).
 *
 * Seletores DKIM conhecidos: os do Brevo (CNAME para o DKIM do Brevo) e os da Hostinger. Um seletor só conta como ativo
 * com chave pública não vazia (`p=`); a Hostinger publica `p=` vazio nos seletores que não usa.
 */
final class SenderDomainCheck {

  public const SPF_OK = 'ok';
  public const SPF_MISSING = 'missing';
  public const SPF_MULTIPLE = 'multiple';

  /**
   * Chave de DMARC quando não há registro, ou o registro não tem política `p=`.
   */
  public const DMARC_MISSING = 'missing';

  /**
   * Seletores DKIM verificados, na ordem de exibição.
   */
  public const DKIM_SELECTORS = ['brevo1', 'brevo2', 'hostingermail-a', 'hostingermail-b', 'hostingermail-c'];

  /**
   * Verifica SPF, DKIM e DMARC do domínio.
   *
   * @param string $domain
   *   Domínio do remetente (ex.: exemplo.invalid).
   *
   * @return array{domain: string, spf: string, dmarc: string, dkim: array<string, bool>}
   *   Domínio, classificação de SPF e de DMARC, e se cada seletor DKIM tem chave ativa.
   */
  public function check(string $domain): array {
    $dkim = [];
    foreach (self::DKIM_SELECTORS as $selector) {
      $dkim[$selector] = self::classifyDkim($this->txt($selector . '._domainkey.' . $domain));
    }
    return [
      'domain' => $domain,
      'spf' => self::classifySpf($this->txt($domain)),
      'dmarc' => self::classifyDmarc($this->txt('_dmarc.' . $domain)),
      'dkim' => $dkim,
    ];
  }

  /**
   * Indica se algum registro do seletor DKIM tem chave pública não vazia.
   *
   * @param string[] $records
   *   Textos TXT do seletor (`<seletor>._domainkey.<domínio>`).
   */
  public static function classifyDkim(array $records): bool {
    foreach ($records as $record) {
      if (preg_match('/(?:^|;)\s*p\s*=\s*([^;\s]+)/i', $record, $match) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Classifica os registros TXT do domínio como SPF.
   *
   * RFC 7208: mais de um registro SPF é erro permanente de avaliação.
   *
   * @param string[] $records
   *   Textos TXT do domínio.
   */
  public static function classifySpf(array $records): string {
    $spf = array_filter($records, static fn(string $record): bool => stripos(trim($record), 'v=spf1') === 0);
    return match (count($spf)) {
      0 => self::SPF_MISSING,
      1 => self::SPF_OK,
      default => self::SPF_MULTIPLE,
    };
  }

  /**
   * Classifica os registros TXT de `_dmarc` e devolve a política (none, quarantine ou reject).
   *
   * @param string[] $records
   *   Textos TXT de `_dmarc.<domínio>`.
   */
  public static function classifyDmarc(array $records): string {
    foreach ($records as $record) {
      if (stripos(trim($record), 'v=DMARC1') !== 0) {
        continue;
      }
      if (preg_match('/(?:^|;)\s*p\s*=\s*(none|quarantine|reject)\s*(?:;|$)/i', trim($record), $match) === 1) {
        return strtolower($match[1]);
      }
      return self::DMARC_MISSING;
    }
    return self::DMARC_MISSING;
  }

  /**
   * Textos TXT do nome, juntando os trechos de cada registro.
   *
   * @return string[]
   *   Textos TXT; vazio quando o DNS não responde ou não há registro.
   */
  private function txt(string $host): array {
    $records = @dns_get_record($host, DNS_TXT);
    if (!is_array($records)) {
      return [];
    }
    $texts = [];
    foreach ($records as $record) {
      $texts[] = isset($record['entries']) ? implode('', $record['entries']) : (string) ($record['txt'] ?? '');
    }
    return $texts;
  }

}

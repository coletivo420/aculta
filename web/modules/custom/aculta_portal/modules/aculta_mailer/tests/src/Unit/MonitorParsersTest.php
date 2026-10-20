<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Monitor\BounceParser;
use Drupal\aculta_mailer\Monitor\DmarcReportParser;
use Drupal\aculta_mailer\Monitor\MimeMessage;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Leitores do monitoramento por IMAP: MIME, relatório DMARC e notificação de falha (DSN).
 */
#[CoversClass(MimeMessage::class)]
#[CoversClass(DmarcReportParser::class)]
#[CoversClass(BounceParser::class)]
#[Group('aculta_mailer')]
final class MonitorParsersTest extends UnitTestCase {

  /**
   * Notificação de falha 5.1.1 com a mensagem original trazendo o correlation ID.
   */
  public function testBounceExtractsPermanentStatusAndCorrelation(): void {
    $raw = implode("\r\n", [
      'From: MAILER-DAEMON@example.invalid',
      'Subject: Undelivered Mail Returned to Sender',
      'Content-Type: multipart/report; report-type=delivery-status; boundary="B1"',
      '',
      '--B1',
      'Content-Type: text/plain',
      '',
      'Delivery failed.',
      '--B1',
      'Content-Type: message/delivery-status',
      '',
      'Reporting-MTA: dns; example.invalid',
      '',
      'Final-Recipient: rfc822; destino@example.invalid',
      'Action: failed',
      'Status: 5.1.1',
      'Diagnostic-Code: smtp; 550 5.1.1 user unknown',
      '',
      '--B1',
      'Content-Type: message/rfc822',
      '',
      'From: naoresponda@example.invalid',
      'X-Aculta-Mail-ID: 0190a3f2-7c1d-4e8a-9b2f-3d4e5f6a7b8c',
      'Subject: teste',
      '',
      'corpo',
      '--B1--',
      '',
    ]);

    $bounce = (new BounceParser())->parse($raw);

    $this->assertNotNull($bounce);
    $this->assertSame('5.1.1', $bounce['status']);
    $this->assertSame('failed', $bounce['action']);
    $this->assertSame('0190a3f2-7c1d-4e8a-9b2f-3d4e5f6a7b8c', $bounce['correlation']);
  }

  /**
   * Mensagem comum (sem DSN) não é tratada como falha.
   */
  public function testOrdinaryMessageIsNotABounce(): void {
    $this->assertNull((new BounceParser())->parse("Subject: oi\r\n\r\ncorpo"));
  }

  /**
   * Relatório DMARC compactado em gzip, resumido em contagens de aprovado e reprovado.
   */
  public function testDmarcGzipReportIsSummarized(): void {
    $xml = '<?xml version="1.0"?><feedback><report_metadata><org_name>google.com</org_name>'
      . '<report_id>123</report_id><date_range><begin>1700000000</begin><end>1700086400</end></date_range>'
      . '</report_metadata><policy_published><domain>example.invalid</domain></policy_published>'
      . '<record><row><source_ip>203.0.113.5</source_ip><count>7</count><policy_evaluated>'
      . '<disposition>none</disposition><dkim>pass</dkim><spf>fail</spf></policy_evaluated></row></record>'
      . '<record><row><source_ip>198.51.100.9</source_ip><count>2</count><policy_evaluated>'
      . '<disposition>none</disposition><dkim>fail</dkim><spf>fail</spf></policy_evaluated></row></record>'
      . '</feedback>';
    $gz = base64_encode(gzencode($xml));
    $raw = implode("\r\n", [
      'Content-Type: multipart/mixed; boundary="M1"',
      '',
      '--M1',
      'Content-Type: text/plain',
      '',
      'Relatório DMARC anexo.',
      '--M1',
      'Content-Type: application/gzip; name="google.com!example.invalid!1700000000!1700086400.xml.gz"',
      'Content-Transfer-Encoding: base64',
      'Content-Disposition: attachment; filename="google.com!example.invalid!1700000000!1700086400.xml.gz"',
      '',
      chunk_split($gz),
      '--M1--',
      '',
    ]);

    $summary = (new DmarcReportParser())->parse($raw);

    $this->assertNotNull($summary);
    $this->assertSame('google.com', $summary['org']);
    $this->assertSame('example.invalid', $summary['domain']);
    $this->assertSame(9, $summary['total']);
    $this->assertSame(7, $summary['pass']);
    $this->assertSame(2, $summary['fail']);
    $this->assertSame(['203.0.113.5' => 7, '198.51.100.9' => 2], $summary['top_sources']);
  }

  /**
   * Cabeçalhos dobrados e corpo em quoted-printable são lidos corretamente.
   */
  public function testFoldedHeadersAndQuotedPrintableBody(): void {
    $raw = "Subject: primeira\r\n  continuação\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nolá=20mundo";

    $headers = MimeMessage::topHeaders($raw);
    $this->assertSame('primeira continuação', $headers['subject']);
    $leaves = MimeMessage::leaves($raw);
    $this->assertSame('olá mundo', $leaves[0]['body']);
  }

}

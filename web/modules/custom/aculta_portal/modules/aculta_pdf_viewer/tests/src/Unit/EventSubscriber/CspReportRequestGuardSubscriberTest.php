<?php

namespace Drupal\Tests\aculta_pdf_viewer\Unit\EventSubscriber;

use Drupal\aculta_pdf_viewer\EventSubscriber\CspReportRequestGuardSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests request validation for the CSP reporting endpoint guard.
 */
#[CoversClass(CspReportRequestGuardSubscriber::class)]
#[Group('aculta_pdf_viewer')]
final class CspReportRequestGuardSubscriberTest extends TestCase {

  /**
   * Tests malformed Reporting API objects are rejected before contrib.
   */
  public function testRejectsObjectForReportsJson(): void {
    $event = $this->requestEvent(
      '{"type":"csp-violation","url":"https://example.test/","body":{}}',
      'application/reports+json',
    );

    (new CspReportRequestGuardSubscriber())->onKernelRequest($event);

    self::assertSame(400, $event->getResponse()?->getStatusCode());
  }

  /**
   * Tests valid Reporting API batches pass through.
   */
  public function testAcceptsReportsJsonList(): void {
    $event = $this->requestEvent(
      '[{"type":"csp-violation","url":"https://example.test/","body":{}}]',
      'application/reports+json',
    );

    (new CspReportRequestGuardSubscriber())->onKernelRequest($event);

    self::assertNull($event->getResponse());
  }

  /**
   * Tests valid legacy CSP reports pass through to the contrib controller.
   */
  public function testAcceptsLegacyCspReport(): void {
    $event = $this->requestEvent(
      '{"csp-report":{"document-uri":"https://example.test/","effective-directive":"script-src"}}',
      'application/csp-report',
    );

    (new CspReportRequestGuardSubscriber())->onKernelRequest($event);

    self::assertNull($event->getResponse());
  }

  /**
   * Tests flattened legacy reports from browsers pass through to contrib.
   */
  public function testAcceptsFlattenedLegacyReport(): void {
    $event = $this->requestEvent(
      '{"type":"csp-violation","url":"https://example.test/","body":{}}',
      'application/csp-report',
    );

    (new CspReportRequestGuardSubscriber())->onKernelRequest($event);

    self::assertNull($event->getResponse());
  }

  /**
   * Tests invalid JSON and malformed legacy reports are rejected.
   */
  #[DataProvider('invalidPayloadProvider')]
  public function testRejectsInvalidPayload(string $content, string $content_type): void {
    $event = $this->requestEvent($content, $content_type);

    (new CspReportRequestGuardSubscriber())->onKernelRequest($event);

    self::assertSame(400, $event->getResponse()?->getStatusCode());
  }

  /**
   * Provides malformed report payloads.
   */
  public static function invalidPayloadProvider(): array {
    return [
      'invalid JSON' => ['{', 'application/reports+json'],
      'scalar JSON' => ['"report"', 'application/reports+json'],
      'scalar list item' => ['["report"]', 'application/reports+json'],
      'incomplete report item' => [
        '[{"type":"csp-violation","url":"https://example.test/"}]',
        'application/reports+json',
      ],
      'empty reporting object' => ['{}', 'application/csp-report'],
      'invalid legacy report' => ['{"csp-report":"invalid"}', 'application/csp-report'],
      'legacy report without document URL' => ['{"csp-report":{}}', 'application/csp-report'],
    ];
  }

  /**
   * Tests report bodies above the configured bound are rejected.
   */
  public function testRejectsOversizedReport(): void {
    $event = $this->requestEvent(str_repeat('x', 65537), 'application/reports+json');

    (new CspReportRequestGuardSubscriber())->onKernelRequest($event);

    self::assertSame(413, $event->getResponse()?->getStatusCode());
  }

  /**
   * Tests requests to unrelated routes are untouched.
   */
  public function testIgnoresOtherRoutes(): void {
    $event = $this->requestEvent('{', 'application/reports+json', '/');

    (new CspReportRequestGuardSubscriber())->onKernelRequest($event);

    self::assertNull($event->getResponse());
  }

  /**
   * Creates a main request event for the Reporting endpoint.
   */
  private function requestEvent(string $content, string $content_type, string $path = '/system/reporting/default'): RequestEvent {
    $request = Request::create($path, 'POST', server: ['CONTENT_TYPE' => $content_type], content: $content);
    $request->attributes->set('_route', $path === '/system/reporting/default' ? 'entity.reporting_endpoint.log' : 'front');
    return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
  }

}

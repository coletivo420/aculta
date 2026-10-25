<?php

namespace Drupal\aculta_pdf_viewer\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rejects malformed or oversized public CSP reports before contrib parses them.
 */
final class CspReportRequestGuardSubscriber implements EventSubscriberInterface {

  /**
   * Maximum report body accepted by the local Reporting endpoint.
   */
  private const MAX_BODY_BYTES = 65536;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // RouterListener runs at priority 32, so the route name is available here.
    return [KernelEvents::REQUEST => ['onKernelRequest', 31]];
  }

  /**
   * Validates request shapes that the Reporting contrib controller assumes.
   */
  public function onKernelRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'entity.reporting_endpoint.log' || !$request->isMethod('POST')) {
      return;
    }

    $content_type = strtolower(trim(explode(';', $request->headers->get('Content-Type', ''), 2)[0]));
    if (!in_array($content_type, ['application/reports+json', 'application/csp-report'], TRUE)) {
      // The contrib controller returns 415 for unsupported media types.
      return;
    }

    $content_length = $request->headers->get('Content-Length');
    if (is_string($content_length) && ctype_digit($content_length) && (int) $content_length > self::MAX_BODY_BYTES) {
      $event->setResponse(new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE, ['Cache-Control' => 'no-store']));
      return;
    }

    $content = $request->getContent();
    if (strlen($content) > self::MAX_BODY_BYTES) {
      $event->setResponse(new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE, ['Cache-Control' => 'no-store']));
      return;
    }

    $report = json_decode($content, TRUE);
    if (!is_array($report) || $report === [] || json_last_error() !== JSON_ERROR_NONE) {
      $event->setResponse(new Response('', Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']));
      return;
    }

    if ($content_type === 'application/reports+json') {
      if (!array_is_list($report)) {
        $event->setResponse(new Response('', Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']));
        return;
      }
      foreach ($report as $item) {
        if (!is_array($item) || !$this->isReportingApiReport($item)) {
          $event->setResponse(new Response('', Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']));
          return;
        }
      }
      return;
    }

    if (array_is_list($report)) {
      $event->setResponse(new Response('', Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']));
      return;
    }

    if (array_key_exists('csp-report', $report)) {
      $legacy_report = $report['csp-report'];
      if (!is_array($legacy_report) || !isset($legacy_report['document-uri']) || !is_string($legacy_report['document-uri']) || trim($legacy_report['document-uri']) === '') {
        $event->setResponse(new Response('', Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']));
      }
      return;
    }

    if (!$this->isReportingApiReport($report)) {
      $event->setResponse(new Response('', Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']));
    }
  }

  /**
   * Checks the fields expected by the contrib Reporting API logger.
   */
  private function isReportingApiReport(array $report): bool {
    return isset($report['type'], $report['url'], $report['body'])
      && is_string($report['type'])
      && trim($report['type']) !== ''
      && is_string($report['url'])
      && trim($report['url']) !== ''
      && is_array($report['body']);
  }

}

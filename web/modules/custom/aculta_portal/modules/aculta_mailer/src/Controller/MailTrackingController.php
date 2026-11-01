<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Controller;

use Drupal\aculta_mailer\Log\MailLogRecorder;
use Drupal\aculta_mailer\Tracking\MailTracker;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abertura (pixel) e clique (redirecionamento) de mensagens rastreadas. Só responde com assinatura válida.
 */
final class MailTrackingController extends ControllerBase {

  /**
   * GIF transparente de 1 pixel.
   */
  private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly MailTracker $tracker,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'), $container->get(MailTracker::class), $container->get('datetime.time'));
  }

  /**
   * Registra uma abertura e devolve o pixel.
   */
  public function open(string $correlation, Request $request): Response {
    $signature = (string) $request->query->get('s', '');
    if (!$this->tracker->verify('o|' . $correlation, $signature)) {
      return new Response('', 404);
    }
    $this->bump($correlation, 'opens', 'first_opened');
    $response = new Response(base64_decode(self::PIXEL), 200, ['Content-Type' => 'image/gif']);
    $response->headers->set('Cache-Control', 'no-store, private');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    return $response;
  }

  /**
   * Registra um clique e redireciona para o link original, desde que seja do mesmo host.
   */
  public function click(string $correlation, Request $request): Response {
    $url = (string) $request->query->get('u', '');
    $signature = (string) $request->query->get('s', '');
    $host = parse_url($url, PHP_URL_HOST);
    if (!$this->tracker->verify('c|' . $correlation . '|' . $url, $signature) || $host !== $request->getHost() || !preg_match('#^https?://#i', $url)) {
      return new Response('', 404);
    }
    $this->bump($correlation, 'clicks', 'first_clicked');
    $response = new RedirectResponse($url, 302);
    $response->headers->set('Cache-Control', 'no-store, private');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
    return $response;
  }

  /**
   * Soma um contador do registro e grava a primeira data. Mensagem sem registro não é contada.
   */
  private function bump(string $correlation, string $counter, string $first): void {
    $storage = $this->entityTypes->getStorage(MailLogRecorder::ENTITY_TYPE);
    $log = reset($storage->loadByProperties(['correlation_id' => $correlation]));
    if (!$log) {
      return;
    }
    $log->set($counter, (int) $log->get($counter)->value + 1);
    if ((int) $log->get($first)->value === 0) {
      $log->set($first, $this->time->getRequestTime());
    }
    $log->save();
  }

}

<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\aculta_portal\Appearance\ColorModePreference;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Saves the authenticated account's color mode preference. */
final class ColorModePreferenceController extends ControllerBase {

  public function __construct(
    private readonly ColorModePreference $preference,
    private readonly AccountProxyInterface $account,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.color_mode_preference'),
      $container->get('current_user'),
      $container->get('cache_tags.invalidator'),
      $container->get('logger.factory')->get('aculta_portal'),
    );
  }

  /**
   * Saves a preference for the current session account only. */
  public function update(Request $request): JsonResponse {
    $uid = (int) $this->account->id();
    if (!$this->account->isAuthenticated() || $uid <= 0) {
      return $this->json(['saved' => FALSE, 'error' => 'authentication_required'], 403);
    }

    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)
      || array_keys($payload) !== ['mode']
      || !is_string($payload['mode'] ?? NULL)
      || !in_array($payload['mode'], ColorModePreference::MODES, TRUE)) {
      return $this->json(['saved' => FALSE, 'error' => 'invalid_mode'], 400);
    }

    try {
      $this->preference->save($uid, $payload['mode']);
      $this->cacheTagsInvalidator->invalidateTags(['user:' . $uid]);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Could not save color mode preference for user @uid: @message', [
        '@uid' => $uid,
        '@message' => $exception->getMessage(),
      ]);
      return $this->json(['saved' => FALSE, 'error' => 'save_failed'], 500);
    }

    return $this->json(['saved' => TRUE, 'mode' => $payload['mode']]);
  }

  /**
   * Builds a private, non-cacheable JSON response.
   *
   * @param array<string, bool|string> $data
   *   Response data.
   * @param int $status
   *   HTTP status code.
   */
  private function json(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->setPrivate();
    $response->headers->addCacheControlDirective('no-store');
    return $response;
  }

}

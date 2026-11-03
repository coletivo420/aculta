<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\aculta_lms\Xapi\XapiAttemptToken;
use Drupal\aculta_lms\Xapi\XapiProgressAccess;
use Drupal\aculta_lms\Xapi\XapiTokenSettings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Issues short-lived tokens bound to a learner and ACULTA course progress.
 */
final class XapiAttemptTokenController {

  public const KEY_ID = 'aculta_lms_xapi_token';

  public function __construct(
    private readonly KeyRepositoryInterface $keys,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly XapiProgressAccess $progressAccess,
    private readonly AccountProxyInterface $currentUser,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Issues a token for ACULTA progress; protocol key remains lesson_status_id.
   */
  public function issue(Request $request): JsonResponse {
    $input = json_decode($request->getContent(), TRUE);
    if (!is_array($input)
      || !isset($input['lesson_status_id'], $input['content'], $input['registration'])
      || !is_numeric($input['lesson_status_id'])
      || (int) $input['lesson_status_id'] <= 0
      || !is_string($input['content'])
      || preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\\S+$/', $input['content']) !== 1
      || !is_string($input['registration'])) {
      return $this->json(['error' => 'Requisição inválida.'], 400);
    }

    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if (!$user instanceof UserInterface || !$user->isActive()
      || !$this->progressAccess->isOwnedBy((int) $input['lesson_status_id'], (int) $user->id())) {
      return $this->json(['error' => 'Tentativa não autorizada.'], 403);
    }

    $email = strtolower(trim((string) $user->getEmail()));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return $this->json(['error' => 'A conta não pode iniciar uma tentativa xAPI.'], 403);
    }
    $actorHash = sha1('mailto:' . $email);
    $key = $this->keys->getKey(self::KEY_ID);
    $secret = $key?->getKeyValue();
    if (!is_string($secret) || strlen($secret) < XapiAttemptToken::MIN_SECRET_BYTES) {
      return $this->json(['error' => 'O serviço xAPI não está configurado.'], 503);
    }

    try {
      $ttl = XapiTokenSettings::ttlFrom($this->configFactory->get(XapiTokenSettings::CONFIG_NAME)->get(XapiTokenSettings::KEY_TTL));
      $token = (new XapiAttemptToken($secret))->issue(
        (int) $user->id(),
        $input['content'],
        $input['registration'],
        (int) $input['lesson_status_id'],
        $actorHash,
        $this->time->getRequestTime(),
        $ttl,
      );
    }
    catch (\Throwable) {
      return $this->json(['error' => 'Requisição inválida.'], 400);
    }

    $response = $this->json([
      'token' => $token,
      'content' => $input['content'],
      'registration' => $input['registration'],
      'actor' => ['mbox_sha1sum' => $actorHash],
      'expires_at' => $this->time->getRequestTime() + $ttl,
    ], 200);
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
  }

  /**
   * Creates a no-store xAPI JSON response. */
  private function json(array $data, int $status): JsonResponse {
    return new JsonResponse($data, $status, ['X-Experience-API-Version' => '2.0']);
  }

}

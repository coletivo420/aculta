<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\aculta_lms\Xapi\XapiAttemptToken;
use Drupal\aculta_lms\Xapi\XapiBatchIngestor;
use Drupal\aculta_lms\Xapi\XapiProgressAccess;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Authenticated POST surface for the ACULTA xAPI 2.0 LRS.
 */
final class XapiStatementsController {

  private const FLOOD_EVENT = 'aculta_lms.xapi.statements';
  private const FLOOD_LIMIT = 60;
  private const FLOOD_WINDOW = 60;
  private const INGEST_LOCK = 'aculta_lms_xapi_ingest';

  public function __construct(
    private readonly KeyRepositoryInterface $keys,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly XapiProgressAccess $progressAccess,
    private readonly FloodInterface $flood,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly XapiBatchIngestor $ingestor,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Receives, validates and persists a batch of statements.
   */
  public function post(Request $request): JsonResponse {
    if ($request->headers->get('X-Experience-API-Version') !== '2.0') {
      return $this->json(['error' => 'Versão xAPI não suportada.'], 400);
    }
    // Simple OAuth also consumes Authorization: Bearer. Keep this private LRS
    // credential in its own header so OAuth cannot reject it before routing.
    if (!preg_match('/^([A-Za-z0-9_.-]{20,4096})$/D', trim((string) $request->headers->get('X-ACULTA-XAPI-Token')), $match)) {
      return $this->json(['error' => 'Credencial inválida.'], 401);
    }
    $token = $match[1];
    $key = $this->keys->getKey(XapiAttemptTokenController::KEY_ID);
    $secret = $key?->getKeyValue();
    if (!is_string($secret) || strlen($secret) < XapiAttemptToken::MIN_SECRET_BYTES) {
      return $this->json(['error' => 'O serviço xAPI não está configurado.'], 503);
    }
    try {
      $claims = (new XapiAttemptToken($secret))->verify($token, $this->time->getRequestTime());
    }
    catch (\Throwable) {
      return $this->json(['error' => 'Credencial inválida.'], 401);
    }

    if (!$this->progressAccess->isOwnedBy((int) $claims['lesson_status_id'], (int) $claims['uid'])) {
      return $this->json(['error' => 'Tentativa não autorizada.'], 401);
    }

    $floodIdentifier = hash('sha256', $token);
    if (!$this->flood->isAllowed(self::FLOOD_EVENT, self::FLOOD_LIMIT, self::FLOOD_WINDOW, $floodIdentifier)) {
      return $this->json(['error' => 'Limite de envios excedido.'], 429);
    }
    $this->flood->register(self::FLOOD_EVENT, self::FLOOD_WINDOW, $floodIdentifier);

    $body = $request->getContent();
    if (strlen($body) > XapiBatchIngestor::MAX_BODY_BYTES) {
      return $this->json(['error' => 'Lote acima do tamanho máximo.'], 400);
    }
    $decoded = json_decode($body, TRUE);
    if (is_array($decoded) && array_is_list($decoded)
      && count($decoded) > XapiBatchIngestor::MAX_STATEMENTS) {
      return $this->json(['error' => 'Lote com declarações demais.'], 400);
    }
    $statementIds = [];
    if (is_array($decoded) && array_is_list($decoded)) {
      foreach ($decoded as $statement) {
        if (is_array($statement) && isset($statement['id']) && is_string($statement['id'])) {
          $statementIds[] = $statement['id'];
        }
      }
    }

    if (!$this->lock->acquire(self::INGEST_LOCK, 0.0)) {
      return $this->json(['error' => 'Ingestão temporariamente ocupada.'], 503);
    }

    try {
      $storage = $this->entityTypeManager->getStorage('aculta_lms_xapi_statement');
      $knownIds = [];
      if ($statementIds !== []) {
        $existing = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('statement_id', array_values(array_unique($statementIds)), 'IN')
          ->execute();
        foreach ($storage->loadMultiple($existing) as $record) {
          if (!$record instanceof ContentEntityInterface) {
            continue;
          }
          $knownIds[] = (string) $record->get('statement_id')->value;
        }
      }

      $claims['actor_mbox_sha1sum'] = strtolower($claims['actor_mbox_sha1sum']);
      $result = $this->ingestor->ingestDecoded($decoded, $claims, $knownIds);
      $transaction = $this->database->startTransaction();
      $acceptedIds = [];
      foreach ($result['accepted'] as $statement) {
        $statementId = (string) $statement['id'];
        $timestamp = isset($statement['timestamp']) ? strtotime($statement['timestamp']) : FALSE;
        $record = $storage->create([
          'statement_id' => $statementId,
          'uid' => $claims['uid'],
          'registration' => $claims['registration'],
          'lesson_status_id' => $claims['lesson_status_id'],
          'verb_id' => $statement['verb']['id'],
          'object_id' => $statement['object']['id'],
          'payload' => (string) json_encode($statement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
          'statement_timestamp' => $timestamp === FALSE ? 0 : $timestamp,
        ]);
        $record->save();
        $acceptedIds[] = $statementId;
      }
      unset($transaction);
      if ($result['refused'] !== []) {
        $this->logger->notice('xAPI recusou {count} declarações para a tentativa {lesson_status_id}.', [
          'count' => count($result['refused']),
          'lesson_status_id' => $claims['lesson_status_id'],
        ]);
      }
      return $this->json($acceptedIds, 200);
    }
    catch (\UnexpectedValueException $exception) {
      return $this->json(['error' => $exception->getMessage()], 400);
    }
    catch (\Throwable $exception) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      $this->logger->error('Falha ao gravar lote xAPI da tentativa {lesson_status_id}: {message}', [
        'lesson_status_id' => $claims['lesson_status_id'],
        'message' => $exception->getMessage(),
      ]);
      return $this->json(['error' => 'Não foi possível gravar as declarações.'], 503);
    }
    finally {
      $this->lock->release(self::INGEST_LOCK);
    }
  }

  /**
   * Creates a no-store xAPI JSON response. */
  private function json(array $data, int $status): JsonResponse {
    $response = new JsonResponse($data, $status, ['X-Experience-API-Version' => '2.0']);
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
  }

}

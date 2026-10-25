<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Site\Settings;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MessageIdFactory;
use Drupal\user\UserDataInterface;
use Drupal\aculta_messenger\Mail\AvisosResumoMailType;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Psr\Log\LoggerInterface;

/**
 * Resumo diário de avisos por e-mail (ACULTA Messenger, F5.2).
 *
 * Envia um e-mail diário por pessoa, com avisos atuais e accessíveis.
 * Exige opt-in, e-mail confirmado e conta ativa. Limpa pendências após sucesso.
 */
final class AvisosResumo {

  private const LANGCODE = 'pt-br';

  /**
   * Digest extension services.
   *
   * @var \Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface[]
   */
  private readonly array $digestProviders;

  /**
   * Providers with unique persistent identities.
   *
   * Calculated once per service instance.
   *
   * @var \Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface[]|null
   */
  private ?array $unambiguousDigestProviders = NULL;

  /**
   * Intervalo mínimo entre dois resumos da mesma conta.
   */
  public const INTERVALO_SEGUNDOS = 20 * 3600;

  public const CHAVE_MAIL = 'avisos_resumo';

  /**
   * Lease covering the configured transport timeout and acknowledgement work.
   */
  private const LOCK_LEASE_SECONDS = 180.0;

  /**
   * The attempt did not send or reconcile a digest.
   */
  private const RESULT_SKIPPED = 0;

  /**
   * This invocation delivered a new digest email.
   */
  private const RESULT_SENT = 1;

  /**
   * A previously accepted digest was reconciled without sending email.
   */
  private const RESULT_RECONCILED = 2;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AvisoPreferencias $preferencias,
    private readonly AcultaMailerInterface $mailer,
    private readonly EmailConfirmationPolicy $emailPolicy,
    private readonly UserDataInterface $userData,
    private readonly TimeInterface $time,
    private readonly AvisoService $avisos,
    private readonly Settings $settings,
    private readonly Connection $database,
    private readonly LockBackendInterface $lock,
    iterable $digestProviders = [],
    private readonly ?LoggerInterface $logger = NULL,
  ) {
    $providers = is_array($digestProviders)
      ? array_values($digestProviders)
      : iterator_to_array($digestProviders);
    // Service discovery order is not a delivery contract. Keep the same
    // digest content and manifest when adapters are registered in a different
    // order after a deployment or cache rebuild.
    usort($providers, function (mixed $a, mixed $b): int {
      if (!$a instanceof AvisoDigestProviderInterface || !$b instanceof AvisoDigestProviderInterface) {
        return strcmp(get_debug_type($a), get_debug_type($b));
      }
      return [$this->digestProviderId($a), get_class($a)] <=> [$this->digestProviderId($b), get_class($b)];
    });
    $this->digestProviders = $providers;
  }

  /**
   * Regra do intervalo: uma conta só recebe um resumo a cada ~20 horas.
   */
  public static function podeEnviar(int $agora, int $ultimoEnvio): bool {
    return $ultimoEnvio === 0 || ($agora - $ultimoEnvio) >= self::INTERVALO_SEGUNDOS;
  }

  /**
   * Envia os resumos pendentes. Retorna quantos novos e-mails foram enviados.
   */
  public function enviarResumos(): int {
    $storage = $this->entityTypeManager->getStorage('aculta_aviso');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('contador_email', 0, '>')->execute();
    $porUsuario = [];
    foreach ($storage->loadMultiple($ids) as $aviso) {
      $porUsuario[(int) $aviso->get('uid')->target_id] = TRUE;
    }
    // An accepted email may still need acknowledgement even when its
    // optional provider was disabled after transport acceptance. Include
    // those recipients so recovery can wait safely for the provider to return.
    $snapshots = $this->userData->get('aculta_messenger', NULL, 'avisos_resumo_em_andamento');
    if (is_array($snapshots)) {
      foreach ($snapshots as $uid => $snapshot) {
        if (is_numeric($uid) && (int) $uid > 0 && is_array($snapshot)) {
          $porUsuario[(int) $uid] ??= [];
        }
      }
    }
    $providers = $this->digestProviders;
    $recipientLookupFailed = FALSE;
    foreach ($providers as $provider) {
      if (!$provider instanceof AvisoDigestProviderInterface) {
        continue;
      }
      try {
        foreach ($provider->pendingRecipientIds() as $uid) {
          $porUsuario[(int) $uid] ??= [];
        }
      }
      catch (\Throwable $exception) {
        // A provider outage must not prevent Forum or other adapters from
        // delivering their independent pending digests during this cron run.
        // Recipients cannot be identified, so do not apply the shared digest
        // cooldown to another source's successful delivery in this run.
        $recipientLookupFailed = TRUE;
        $this->logger?->error('Daily notice digest recipient lookup failed for provider @provider with @exception.', [
          '@provider' => get_class($provider),
          '@exception' => get_class($exception),
        ]);
      }
    }
    $enviados = 0;
    foreach ($porUsuario as $uid => $_) {
      try {
        if ($this->enviarPara((int) $uid, $recipientLookupFailed) === self::RESULT_SENT) {
          $enviados++;
        }
      }
      catch (\Throwable $exception) {
        // A corrupt provider row or persistence failure for one recipient
        // must not prevent unrelated recipients from receiving their digest.
        $this->logger?->error('Daily notice digest processing failed for one recipient with @exception.', [
          '@exception' => get_class($exception),
        ]);
      }
    }
    return $enviados;
  }

  /**
   * Envia o resumo combinado da pessoa.
   *
   * @param int $uid
   *   Identificador da pessoa destinatária.
   * @param bool $recipientLookupFailed
   *   Whether a provider failed to enumerate its pending recipients.
   */
  private function enviarPara(int $uid, bool $recipientLookupFailed): int {
    $lockName = 'aculta_messenger.avisos_resumo.' . $uid;
    if (!$this->lock->acquire($lockName, self::LOCK_LEASE_SECONDS)) {
      return self::RESULT_SKIPPED;
    }
    try {
      return $this->enviarParaComLock($uid, $lockName, $recipientLookupFailed);
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Sends one combined digest while holding the recipient lock.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param string $lockName
   *   Recipient lock name held for this processing attempt.
   * @param bool $recipientLookupFailed
   *   Whether a provider failed to enumerate its pending recipients.
   */
  private function enviarParaComLock(int $uid, string $lockName, bool $recipientLookupFailed): int {
    $conta = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($conta === NULL) {
      return self::RESULT_SKIPPED;
    }
    // Recover the exact batch if the Mailer accepted it but a later
    // acknowledgement failed. This prevents a newly grown pending batch from
    // receiving a different correlation and resending already delivered rows.
    $recuperacao = $this->recuperarDigestAceito(
      $uid,
      (string) $conta->getEmail(),
      $recipientLookupFailed,
    );
    if ($recuperacao !== NULL) {
      return $recuperacao ? self::RESULT_RECONCILED : self::RESULT_SKIPPED;
    }
    if (!$this->preferencias->resumoEmailLigado($uid)) {
      foreach ($this->forumAvisosPendentes($uid) as $aviso) {
        $this->avisos->clearPendingEmailCount(
          $uid,
          (int) $aviso->get('assunto_nid')->value,
          (string) $aviso->get('tipo')->value,
        );
      }
      foreach ($this->digestProviders as $provider) {
        if ($provider instanceof AvisoDigestProviderInterface) {
          $provider->discardDigest($uid);
        }
      }
      return self::RESULT_SKIPPED;
    }
    if (!$conta->isActive() || $conta->getEmail() === '' || !$this->emailPolicy->isConfirmed($conta)) {
      return self::RESULT_SKIPPED;
    }
    $agora = $this->time->getRequestTime();
    if (!self::podeEnviar($agora, (int) $this->userData->get('aculta_messenger', $uid, 'avisos_resumo_ultimo'))) {
      return self::RESULT_SKIPPED;
    }
    // Re-read under the recipient lock so concurrent event aggregation cannot
    // be reset after its count was missed by this digest.
    $avisos = $this->forumAvisosPendentes($uid);
    $forumGenerations = $this->avisos->digestBatchGenerations($uid, array_map(
      static fn(Aviso $aviso): int => (int) $aviso->get('assunto_nid')->value,
      $avisos,
    ));
    $linhas = [];
    $daFila = [];
    foreach ($avisos as $aviso) {
      $nid = (int) $aviso->get('assunto_nid')->value;
      if (!$this->avisos->canRecipientViewTopic($uid, $nid)) {
        // Acesso revogado: não reter este item para um resumo futuro.
        $this->avisos->clearPendingEmailCount($uid, $nid, (string) $aviso->get('tipo')->value);
        continue;
      }
      if (!$this->preferencias->tipoNoResumo($uid, (string) $aviso->get('tipo')->value)) {
        // A switch to direct/off must not leave these counts to reappear in a
        // later digest after the user changes their preference again.
        if ((int) $aviso->get('contador_email')->value > 0) {
          $this->avisos->clearPendingEmailCount($uid, $nid, (string) $aviso->get('tipo')->value);
        }
        continue;
      }
      $quantidade = (int) $aviso->get('contador_email')->value;
      $daFila[] = [
        'nid' => $nid,
        'tipo' => (string) $aviso->get('tipo')->value,
        'quantidade' => $quantidade,
        'geracao' => $forumGenerations[$nid] ?? 0,
      ];
      $linhas[] = [
        'tipo' => (string) $aviso->get('tipo')->value,
        'titulo' => (string) $aviso->get('titulo')->value,
        'quantidade' => $quantidade,
      ];
    }
    $providersWithRows = [];
    $providerLookupFailed = $recipientLookupFailed;
    foreach ($this->uniqueDigestProviders() as $provider) {
      if (!$provider instanceof AvisoDigestProviderInterface) {
        continue;
      }
      try {
        $providerRows = $provider->digestRowsFor($uid);
      }
      catch (\Throwable $exception) {
        // Providers whose pending-row query fails stay retryable and do not
        // block independent sources for this recipient.
        $providerLookupFailed = TRUE;
        $this->logger?->error('Daily notice digest row lookup failed for provider @provider with @exception.', [
          '@provider' => get_class($provider),
          '@exception' => get_class($exception),
        ]);
        continue;
      }
      if ($providerRows === []) {
        continue;
      }
      // Providers can join persisted rows through multiple relations. Sort
      // before de-duplicating so malformed duplicate IDs cannot make the body
      // depend on database row order while sharing the same correlation ID.
      usort($providerRows, static function (mixed $a, mixed $b): int {
        $key = static fn(mixed $row): array => is_array($row) ? [
          is_int($row['delivery_id'] ?? NULL) ? $row['delivery_id'] : PHP_INT_MAX,
          is_string($row['tipo'] ?? NULL) ? $row['tipo'] : '',
          is_string($row['titulo'] ?? NULL) ? $row['titulo'] : '',
          is_int($row['quantidade'] ?? NULL) ? $row['quantidade'] : 0,
        ] : [PHP_INT_MAX, '', '', 0];
        return $key($a) <=> $key($b);
      });
      $eligibleRowsByDeliveryId = [];
      foreach ($providerRows as $row) {
        if (!is_array($row) || !is_int($row['delivery_id'] ?? NULL) || $row['delivery_id'] < 1) {
          continue;
        }
        // Category is required by the provider contract. A malformed row must
        // never bypass the account's category-level email policy.
        if (!is_string($row['tipo'] ?? NULL) || trim($row['tipo']) === '') {
          continue;
        }
        if ($this->preferencias->emailMode($uid, $row['tipo']) !== AvisoPreferencias::EMAIL_DIGEST) {
          continue;
        }
        // Provider implementations are extensible and may join through more
        // than one relation. A delivery ID represents one persisted notice;
        // never repeat that notice in the email or its idempotency manifest.
        $eligibleRowsByDeliveryId[$row['delivery_id']] ??= $row;
      }
      $eligibleRows = array_values($eligibleRowsByDeliveryId);
      if ($eligibleRows === []) {
        // Preferences can change after a provider queued a digest row. Clear
        // those pending records instead of sending after the user opted out
        // or switched that category to direct delivery.
        $provider->discardDigest($uid);
        continue;
      }
      array_push($linhas, ...$eligibleRows);
      $providersWithRows[] = [
        'provider' => $provider,
        'provider_id' => $this->digestProviderId($provider),
        'manifest_rows' => array_map(static fn(array $row): array => [
          'tipo' => (string) ($row['tipo'] ?? ''),
          'delivery_id' => (int) $row['delivery_id'],
          'quantidade' => (int) ($row['quantidade'] ?? 1),
        ], $eligibleRows),
        'delivery_ids' => array_values(array_unique(array_map(
          static fn(array $row): int => (int) ($row['delivery_id'] ?? 0),
          $eligibleRows,
        ))),
      ];
    }
    if ($linhas === []) {
      return self::RESULT_SKIPPED;
    }
    $entregasManifesto = [];
    foreach ($daFila as $aviso) {
      $entregasManifesto[] = [
        'origem' => 'forum',
        'nid' => $aviso['nid'],
        'tipo' => $aviso['tipo'],
        'quantidade' => $aviso['quantidade'],
        'geracao' => $aviso['geracao'],
      ];
    }
    foreach ($providersWithRows as $providerRows) {
      foreach ($providerRows['manifest_rows'] as $manifestRow) {
        $entregasManifesto[] = [
          'origem' => $providerRows['provider_id'],
          'id' => $manifestRow['delivery_id'],
          'tipo' => $manifestRow['tipo'],
          'quantidade' => $manifestRow['quantidade'],
        ];
      }
    }
    usort($entregasManifesto, static fn(array $a, array $b): int => strcmp(serialize($a), serialize($b)));
    $hashSalt = (string) $this->settings->get('hash_salt', '');
    $recipientKey = hash_hmac('sha256', mb_strtolower(trim((string) $conta->getEmail())), $hashSalt);
    $manifesto = hash_hmac('sha256', serialize([
      'uid' => $uid,
      'recipient' => $recipientKey,
      'entregas' => $entregasManifesto,
    ]), $hashSalt);
    $correlationId = MessageIdFactory::correlationIdFor($manifesto);
    $mensagem = new MailMessage(self::CHAVE_MAIL, (string) $conta->getEmail(), self::LANGCODE, [
      'resumo' => AvisosResumoMailType::formatLinhas($linhas),
    ], $correlationId);
    $snapshot = [
      'correlation_id' => $correlationId,
      'created' => $agora,
      'provider_lookup_failed' => $providerLookupFailed,
      'forum' => $daFila,
      'providers' => array_map(static fn(array $providerRows): array => [
        'provider_id' => $providerRows['provider_id'],
        'delivery_ids' => $providerRows['delivery_ids'],
      ], $providersWithRows),
    ];
    // Store IDs and counters only (no recipient address or notice content) so
    // the accepted transport can be reconciled after a process interruption.
    $this->userData->set('aculta_messenger', $uid, 'avisos_resumo_em_andamento', $snapshot);
    try {
      $resultado = $this->mailer->deliver($mensagem);
    }
    catch (\Throwable $exception) {
      // Keep all entries pending for cron retry; avoid logging addresses,
      // notice content, or the transport exception message.
      $this->logger?->error('Daily notice digest delivery raised @exception; pending rows remain available for retry.', [
        '@exception' => get_class($exception),
      ]);
      return self::RESULT_SKIPPED;
    }
    // A lock lease can expire during a stalled transport. Drupal's lock API
    // renews a lock held by this backend and returns FALSE if another worker
    // has taken it. A stale worker must not acknowledge or discard rows after
    // losing ownership, even when its own transport returned successfully.
    if (!$this->lock->acquire($lockName, self::LOCK_LEASE_SECONDS)) {
      $this->logger?->warning('Daily notice digest lost its recipient lock during delivery; acknowledgement is deferred.');
      return self::RESULT_SKIPPED;
    }
    if (!$resultado->sent) {
      if ($resultado->cancelled || $resultado->error === DeliveryError::Permanent) {
        // Suspensão administrativa e erros definitivos encerram apenas o canal
        // de e-mail; os avisos obrigatórios no site continuam disponíveis.
        foreach ($daFila as $aviso) {
          $this->avisos->clearPendingEmailCount($uid, $aviso['nid'], $aviso['tipo']);
        }
        foreach ($providersWithRows as $providerRows) {
          $providerRows['provider']->discardDigest($uid);
        }
        $this->userData->delete('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
      }
      return self::RESULT_SKIPPED;
    }
    // Persist proof of acceptance alongside the exact recovery manifest. The
    // Mailer log can expire while an optional adapter is unavailable; keeping
    // this marker lets recovery acknowledge the original batch without
    // depending on the log's retention window or resending it.
    $snapshot['accepted_at'] = $agora;
    $this->userData->set('aculta_messenger', $uid, 'avisos_resumo_em_andamento', $snapshot);
    // Every adapter uses Drupal's shared database connection. Commit all
    // acknowledgements together so a failed source leaves the exact same batch
    // available for the stable Mailer correlation on the next cron run.
    $transaction = $this->database->startTransaction();
    try {
      foreach ($daFila as $aviso) {
        $this->avisos->acknowledgeEmailCount($uid, $aviso['nid'], $aviso['tipo'], $aviso['quantidade']);
      }
      foreach ($providersWithRows as $providerRows) {
        $deliveryIds = array_values(array_filter($providerRows['delivery_ids'], static fn(int $id): bool => $id > 0));
        if ($deliveryIds !== []) {
          $providerRows['provider']->markDigestSent($uid, $deliveryIds);
        }
      }
      // Do not postpone a failed source for 20 hours just because another
      // source delivered successfully in this combined digest.
      if (!$providerLookupFailed) {
        $this->userData->set('aculta_messenger', $uid, 'avisos_resumo_ultimo', $agora);
      }
      $this->userData->delete('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
      $transaction->commitOrRelease();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
    return self::RESULT_SENT;
  }

  /**
   * Reconciles or defers the exact batch saved before Mailer delivery.
   *
   * A recent unaccepted snapshot may belong to a transport that is still
   * running after its recipient lock lease expired. Keep that manifest stable
   * until the transport timeout window has elapsed, rather than building a
   * different correlation from a batch that grew during the in-flight send.
   *
   * @return bool|null
   *   TRUE when an accepted snapshot was reconciled, FALSE when another
   *   attempt should wait for the in-flight transport window, or NULL when no
   *   usable snapshot exists and the current pending batch can be built.
   */
  private function recuperarDigestAceito(int $uid, string $recipient, bool $recipientLookupFailed): ?bool {
    $snapshot = $this->userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
    if (!is_array($snapshot) || !is_string($snapshot['correlation_id'] ?? NULL)) {
      if ($snapshot !== NULL) {
        $this->userData->delete('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
      }
      return NULL;
    }
    $acceptedAt = is_int($snapshot['accepted_at'] ?? NULL) && $snapshot['accepted_at'] > 0
      ? $snapshot['accepted_at']
      : NULL;
    if ($acceptedAt === NULL && !$this->mailer->wasAcceptedCorrelation($snapshot['correlation_id'], self::CHAVE_MAIL, $recipient)) {
      $created = (int) ($snapshot['created'] ?? 0);
      if ($created > 0 && ($this->time->getRequestTime() - $created) < self::LOCK_LEASE_SECONDS) {
        // The original worker may still be inside its Mailer call after its
        // lock expired. Keep the exact correlation for a later retry.
        $this->logger?->notice('Daily notice digest is waiting for its in-flight delivery window before retrying.');
        return FALSE;
      }
      // The transport window elapsed without acceptance. Rebuild from current
      // pending rows; an unchanged batch retains its deterministic correlation.
      $this->userData->delete('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
      return NULL;
    }
    if ($acceptedAt === NULL) {
      // Upgrade pre-marker snapshots as soon as the Mailer confirms acceptance.
      // This durable evidence outlives its privacy-bounded delivery log.
      $snapshot['accepted_at'] = $this->time->getRequestTime();
      $this->userData->set('aculta_messenger', $uid, 'avisos_resumo_em_andamento', $snapshot);
    }

    $providers = [];
    $uniqueProviders = $this->uniqueDigestProviders();
    $classCounts = array_count_values(array_map(
      static fn(AvisoDigestProviderInterface $provider): string => get_class($provider),
      $uniqueProviders,
    ));
    foreach ($uniqueProviders as $provider) {
      $providers[$this->digestProviderId($provider)] = $provider;
      // Compatibility for accepted snapshots written before stable provider
      // IDs were added. A PHP class alias is safe only when it resolves to one
      // configured provider; otherwise leave recovery pending.
      $class = get_class($provider);
      if (($classCounts[$class] ?? 0) === 1) {
        $providers[$class] = $provider;
      }
    }
    foreach (($snapshot['providers'] ?? []) as $providerSnapshot) {
      $providerKey = $providerSnapshot['provider_id'] ?? $providerSnapshot['class'] ?? '';
      if (!is_string($providerKey) || !isset($providers[$providerKey])) {
        // Keep the snapshot until its optional adapter is enabled again; a
        // second email would duplicate a batch the Mailer already accepted.
        $this->logger?->error('Accepted digest awaits unavailable adapter @provider acknowledgement.', [
          '@provider' => $providerKey ?: 'unknown',
        ]);
        return TRUE;
      }
    }

    $transaction = $this->database->startTransaction();
    try {
      foreach (($snapshot['forum'] ?? []) as $notice) {
        if (is_array($notice) && (int) ($notice['nid'] ?? 0) > 0) {
          $this->avisos->acknowledgeEmailCount(
            $uid,
            (int) $notice['nid'],
            (string) ($notice['tipo'] ?? ''),
            (int) ($notice['quantidade'] ?? 0),
          );
        }
      }
      foreach (($snapshot['providers'] ?? []) as $providerSnapshot) {
        $providerKey = $providerSnapshot['provider_id'] ?? $providerSnapshot['class'] ?? '';
        $provider = $providers[$providerKey];
        $deliveryIds = array_values(array_filter(
          array_map('intval', $providerSnapshot['delivery_ids'] ?? []),
          static fn(int $id): bool => $id > 0,
        ));
        if ($deliveryIds !== []) {
          $provider->markDigestSent($uid, $deliveryIds);
        }
      }
      if (empty($snapshot['provider_lookup_failed']) && !$recipientLookupFailed) {
        $this->userData->set('aculta_messenger', $uid, 'avisos_resumo_ultimo', (int) ($snapshot['created'] ?? $this->time->getRequestTime()));
      }
      $this->userData->delete('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
      $transaction->commitOrRelease();
      return TRUE;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Loads the current pending Forum aggregates for one recipient.
   *
   * @return \Drupal\aculta_messenger\Entity\Aviso[]
   *   Current email-pending Forum notice aggregates.
   */
  private function forumAvisosPendentes(int $uid): array {
    $storage = $this->entityTypeManager->getStorage('aculta_aviso');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('contador_email', 0, '>')
      ->execute();
    return array_values($storage->loadMultiple($ids));
  }

  /**
   * Gets an adapter's stable ID or the legacy class-based identity.
   */
  private function digestProviderId(AvisoDigestProviderInterface $provider): string {
    return $provider instanceof AvisoDigestProviderIdentityInterface
      ? $provider->digestProviderId()
      : get_class($provider);
  }

  /**
   * Returns providers whose persisted acknowledgement identity is unambiguous.
   *
   * Accepted snapshots store provider IDs and delivery IDs, not PHP service
   * IDs. If two configured providers claim the same identity, acknowledgement
   * could be applied to the wrong source. Keep those rows pending and let
   * independent providers continue.
   *
   * @return \Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface[]
   *   Providers with unique identities.
   */
  private function uniqueDigestProviders(): array {
    if ($this->unambiguousDigestProviders !== NULL) {
      return $this->unambiguousDigestProviders;
    }

    $counts = [];
    foreach ($this->digestProviders as $provider) {
      if ($provider instanceof AvisoDigestProviderInterface) {
        $id = $this->digestProviderId($provider);
        $counts[$id] = ($counts[$id] ?? 0) + 1;
      }
    }

    $providers = [];
    $logged = [];
    foreach ($this->digestProviders as $provider) {
      if (!$provider instanceof AvisoDigestProviderInterface) {
        continue;
      }
      $id = $this->digestProviderId($provider);
      if ($id === '' || ($counts[$id] ?? 0) !== 1) {
        if (!isset($logged[$id])) {
          $this->logger?->error('Daily notice digest skipped providers with a missing or duplicate identity.');
          $logged[$id] = TRUE;
        }
        continue;
      }
      $providers[] = $provider;
    }
    return $this->unambiguousDigestProviders = $providers;
  }

}

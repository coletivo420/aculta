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
   * Intervalo mínimo entre dois resumos da mesma conta.
   */
  public const INTERVALO_SEGUNDOS = 20 * 3600;

  public const CHAVE_MAIL = 'avisos_resumo';

  /**
   * Lease covering the configured transport timeout and acknowledgement work.
   */
  private const LOCK_LEASE_SECONDS = 180.0;

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
    $this->digestProviders = is_array($digestProviders)
      ? array_values($digestProviders)
      : iterator_to_array($digestProviders);
  }

  /**
   * Regra do intervalo: uma conta só recebe um resumo a cada ~20 horas.
   */
  public static function podeEnviar(int $agora, int $ultimoEnvio): bool {
    return $ultimoEnvio === 0 || ($agora - $ultimoEnvio) >= self::INTERVALO_SEGUNDOS;
  }

  /**
   * Envia os resumos pendentes. Retorna quantos e-mails foram enviados.
   */
  public function enviarResumos(): int {
    $storage = $this->entityTypeManager->getStorage('aculta_aviso');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('contador_email', 0, '>')->execute();
    $porUsuario = [];
    foreach ($storage->loadMultiple($ids) as $aviso) {
      $porUsuario[(int) $aviso->get('uid')->target_id] = TRUE;
    }
    $providers = $this->digestProviders;
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
        $this->logger?->error('Daily notice digest recipient lookup failed for provider @provider with @exception.', [
          '@provider' => get_class($provider),
          '@exception' => get_class($exception),
        ]);
      }
    }
    $enviados = 0;
    foreach ($porUsuario as $uid => $_) {
      try {
        if ($this->enviarPara((int) $uid)) {
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
   */
  private function enviarPara(int $uid): bool {
    $lockName = 'aculta_messenger.avisos_resumo.' . $uid;
    if (!$this->lock->acquire($lockName, self::LOCK_LEASE_SECONDS)) {
      return FALSE;
    }
    try {
      return $this->enviarParaComLock($uid);
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
   */
  private function enviarParaComLock(int $uid): bool {
    $conta = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($conta === NULL) {
      return FALSE;
    }
    // Recover the exact batch if the Mailer accepted it but a later
    // acknowledgement failed. This prevents a newly grown pending batch from
    // receiving a different correlation and resending already delivered rows.
    if ($this->recuperarDigestAceito($uid, (string) $conta->getEmail())) {
      return TRUE;
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
      return FALSE;
    }
    if (!$conta->isActive() || $conta->getEmail() === '' || !$this->emailPolicy->isConfirmed($conta)) {
      return FALSE;
    }
    $agora = $this->time->getRequestTime();
    if (!self::podeEnviar($agora, (int) $this->userData->get('aculta_messenger', $uid, 'avisos_resumo_ultimo'))) {
      return FALSE;
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
    $providerLookupFailed = FALSE;
    foreach ($this->digestProviders as $provider) {
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
      $eligibleRows = array_values(array_filter(
        $providerRows,
        fn(array $row): bool => is_int($row['delivery_id'] ?? NULL)
          && $row['delivery_id'] > 0
          && (($row['tipo'] ?? NULL) === NULL
            || $this->preferencias->emailMode($uid, (string) $row['tipo']) === AvisoPreferencias::EMAIL_DIGEST),
      ));
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
      return FALSE;
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
          'origem' => get_class($providerRows['provider']),
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
        'class' => get_class($providerRows['provider']),
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
      return FALSE;
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
      return FALSE;
    }
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
    return TRUE;
  }

  /**
   * Reconciles the exact batch left before an accepted Mailer acknowledgement.
   */
  private function recuperarDigestAceito(int $uid, string $recipient): bool {
    $snapshot = $this->userData->get('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
    if (!is_array($snapshot) || !is_string($snapshot['correlation_id'] ?? NULL)) {
      if ($snapshot !== NULL) {
        $this->userData->delete('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
      }
      return FALSE;
    }
    if (!$this->mailer->wasAcceptedCorrelation($snapshot['correlation_id'], self::CHAVE_MAIL, $recipient)) {
      // A retryable/non-accepted attempt can be rebuilt from current pending
      // rows. Its stable correlation will be reused if the batch is unchanged.
      $this->userData->delete('aculta_messenger', $uid, 'avisos_resumo_em_andamento');
      return FALSE;
    }

    $providers = [];
    foreach ($this->digestProviders as $provider) {
      if ($provider instanceof AvisoDigestProviderInterface) {
        $providers[get_class($provider)] = $provider;
      }
    }
    foreach (($snapshot['providers'] ?? []) as $providerSnapshot) {
      if (!isset($providers[$providerSnapshot['class'] ?? ''])) {
        // Keep the snapshot until its optional adapter is enabled again; a
        // second email would duplicate a batch the Mailer already accepted.
        $this->logger?->error('Accepted digest awaits unavailable adapter @provider acknowledgement.', [
          '@provider' => (string) ($providerSnapshot['class'] ?? 'unknown'),
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
        $provider = $providers[$providerSnapshot['class']];
        $deliveryIds = array_values(array_filter(
          array_map('intval', $providerSnapshot['delivery_ids'] ?? []),
          static fn(int $id): bool => $id > 0,
        ));
        if ($deliveryIds !== []) {
          $provider->markDigestSent($uid, $deliveryIds);
        }
      }
      if (empty($snapshot['provider_lookup_failed'])) {
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

}

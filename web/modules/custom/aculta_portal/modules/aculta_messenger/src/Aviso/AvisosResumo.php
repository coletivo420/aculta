<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\user\UserDataInterface;
use Drupal\aculta_messenger\Mail\AvisosResumoMailType;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;

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

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AvisoPreferencias $preferencias,
    private readonly AcultaMailerInterface $mailer,
    private readonly EmailConfirmationPolicy $emailPolicy,
    private readonly UserDataInterface $userData,
    private readonly TimeInterface $time,
    private readonly AvisoService $avisos,
    private readonly UuidInterface $uuid,
    iterable $digestProviders = [],
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
      $porUsuario[(int) $aviso->get('uid')->target_id][] = $aviso;
    }
    $providers = $this->digestProviders;
    foreach ($providers as $provider) {
      if (!$provider instanceof AvisoDigestProviderInterface) {
        continue;
      }
      foreach ($provider->pendingRecipientIds() as $uid) {
        $porUsuario[(int) $uid] ??= [];
      }
    }
    $enviados = 0;
    foreach ($porUsuario as $uid => $avisos) {
      if (!$this->preferencias->resumoEmailLigado($uid)) {
        // Opt-out: limpa pendências para não acumular avisos antigos.
        foreach ($avisos as $aviso) {
          $aviso->set('contador_email', 0);
          $aviso->save();
        }
        foreach ($providers as $provider) {
          if ($provider instanceof AvisoDigestProviderInterface) {
            $provider->discardDigest((int) $uid);
          }
        }
        continue;
      }
      if ($this->enviarPara($uid, $avisos)) {
        $enviados++;
      }
    }
    return $enviados;
  }

  /**
   * Envia o resumo combinado da pessoa.
   *
   * @param int $uid
   *   Identificador da pessoa destinatária.
   * @param \Drupal\aculta_messenger\Entity\Aviso[] $avisos
   *   Avisos do Fórum pendentes.
   */
  private function enviarPara(int $uid, array $avisos): bool {
    $conta = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($conta === NULL || !$conta->isActive() || $conta->getEmail() === '' || !$this->emailPolicy->isConfirmed($conta)) {
      return FALSE;
    }
    $agora = $this->time->getRequestTime();
    if (!self::podeEnviar($agora, (int) $this->userData->get('aculta_messenger', $uid, 'avisos_resumo_ultimo'))) {
      return FALSE;
    }
    $linhas = [];
    $daFila = [];
    foreach ($avisos as $aviso) {
      $nid = (int) $aviso->get('assunto_nid')->value;
      if (!$this->avisos->canRecipientViewTopic($uid, $nid)) {
        // Acesso revogado: não reter este item para um resumo futuro.
        $aviso->set('contador_email', 0);
        $aviso->save();
        continue;
      }
      if (!$this->preferencias->tipoNoResumo($uid, (string) $aviso->get('tipo')->value)) {
        continue;
      }
      $daFila[] = $aviso;
      $linhas[] = [
        'tipo' => (string) $aviso->get('tipo')->value,
        'titulo' => (string) $aviso->get('titulo')->value,
        'quantidade' => (int) $aviso->get('contador_email')->value,
      ];
    }
    $providersWithRows = [];
    foreach ($this->digestProviders as $provider) {
      if (!$provider instanceof AvisoDigestProviderInterface) {
        continue;
      }
      $providerRows = $provider->digestRowsFor($uid);
      if ($providerRows !== []) {
        array_push($linhas, ...$providerRows);
        $providersWithRows[] = $provider;
      }
    }
    if ($linhas === []) {
      return FALSE;
    }
    $mensagem = new MailMessage(self::CHAVE_MAIL, (string) $conta->getEmail(), self::LANGCODE, [
      'resumo' => AvisosResumoMailType::formatLinhas($linhas),
    ], $this->uuid->generate());
    if (!$this->mailer->send($mensagem)) {
      return FALSE;
    }
    foreach ($daFila as $aviso) {
      $aviso->set('contador_email', 0);
      $aviso->save();
    }
    foreach ($providersWithRows as $provider) {
      $provider->markDigestSent($uid);
    }
    $this->userData->set('aculta_messenger', $uid, 'avisos_resumo_ultimo', $agora);
    return TRUE;
  }

}

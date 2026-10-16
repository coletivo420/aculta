<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\user\UserDataInterface;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;

/**
 * Resumo diário dos avisos por e-mail (ACULTA Messenger, F5.2, política de 2026-10-16).
 *
 * Envia um e-mail por pessoa por dia, só quando há avisos com novidade para o e-mail (contador_email > 0), agrupados
 * por tópico. Exige opção ligada, e-mail confirmado e conta ativa. Zera o contador de e-mail só após o envio.
 */
final class AvisosResumo {

  /** Intervalo mínimo entre dois resumos da mesma conta. */
  public const INTERVALO_SEGUNDOS = 20 * 3600;

  public const CHAVE_MAIL = 'avisos_resumo';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AvisoPreferencias $preferencias,
    private readonly MailManagerInterface $mailManager,
    private readonly EmailConfirmationPolicy $emailPolicy,
    private readonly UserDataInterface $userData,
    private readonly TimeInterface $time,
  ) {}

  /** Regra do intervalo: uma conta só recebe um resumo a cada ~20 horas. */
  public static function podeEnviar(int $agora, int $ultimoEnvio): bool {
    return $ultimoEnvio === 0 || ($agora - $ultimoEnvio) >= self::INTERVALO_SEGUNDOS;
  }

  /** Envia os resumos pendentes. Retorna quantos e-mails foram enviados. */
  public function enviarResumos(): int {
    $storage = $this->entityTypeManager->getStorage('aculta_aviso');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('contador_email', 0, '>')->execute();
    $porUsuario = [];
    foreach ($storage->loadMultiple($ids) as $aviso) {
      $porUsuario[(int) $aviso->get('uid')->target_id][] = $aviso;
    }
    $enviados = 0;
    foreach ($porUsuario as $uid => $avisos) {
      if (!$this->preferencias->resumoEmailLigado($uid)) {
        // Desligado: os contadores de e-mail são zerados, para não acumular um resumo antigo.
        foreach ($avisos as $aviso) {
          $aviso->set('contador_email', 0);
          $aviso->save();
        }
        continue;
      }
      if ($this->enviarPara($uid, $avisos)) {
        $enviados++;
      }
    }
    return $enviados;
  }

  /** @param \Drupal\aculta_messenger\Entity\Aviso[] $avisos */
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
    if ($linhas === []) {
      return FALSE;
    }
    $resultado = $this->mailManager->mail('aculta_messenger', self::CHAVE_MAIL, $conta->getEmail(), LanguageInterface::LANGCODE_DEFAULT, [
      'linhas' => $linhas,
    ]);
    if (empty($resultado['result'])) {
      return FALSE;
    }
    foreach ($daFila as $aviso) {
      $aviso->set('contador_email', 0);
      $aviso->save();
    }
    $this->userData->set('aculta_messenger', $uid, 'avisos_resumo_ultimo', $agora);
    return TRUE;
  }

}

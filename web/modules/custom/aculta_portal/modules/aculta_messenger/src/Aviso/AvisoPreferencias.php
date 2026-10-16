<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserDataInterface;
use Drupal\aculta_messenger\Entity\Aviso;

/**
 * Controles individuais de avisos (ACULTA Messenger, F5.2).
 *
 * Os avisos no site são obrigatórios e não podem ser desligados. As preferências individuais controlam só o resumo
 * por e-mail: a chave geral (desligada por padrão) e, dentro dela, cada tipo de aviso. Guardadas em user.data.
 */
final class AvisoPreferencias {

  public const MODULO = 'aculta_messenger';

  public function __construct(
    private readonly UserDataInterface $userData,
  ) {}

  /** O resumo diário por e-mail está ligado para esta conta? (opt-in, desligado por padrão) */
  public function resumoEmailLigado(int $uid): bool {
    return (bool) $this->userData->get(self::MODULO, $uid, 'avisos_resumo_email');
  }

  /** Esta conta recebe no resumo por e-mail o tipo informado? (ligado por padrão quando o resumo está ligado) */
  public function tipoNoResumo(int $uid, string $tipo): bool {
    $valor = $this->userData->get(self::MODULO, $uid, 'avisos_resumo_' . $tipo);
    return $valor === NULL ? TRUE : (bool) $valor;
  }

  /** Grava as preferências. Não altera os avisos no site. */
  public function salvar(int $uid, bool $resumoEmail, bool $respostas, bool $atualizacoes): void {
    $this->userData->set(self::MODULO, $uid, 'avisos_resumo_email', $resumoEmail ? 1 : 0);
    $this->userData->set(self::MODULO, $uid, 'avisos_resumo_' . Aviso::TIPO_RESPOSTA, $respostas ? 1 : 0);
    $this->userData->set(self::MODULO, $uid, 'avisos_resumo_' . Aviso::TIPO_ATUALIZACAO, $atualizacoes ? 1 : 0);
  }

}

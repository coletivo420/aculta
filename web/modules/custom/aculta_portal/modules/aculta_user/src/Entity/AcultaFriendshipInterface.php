<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Stores one directional request and its state for a member pair. */
interface AcultaFriendshipInterface extends ContentEntityInterface {

  /**
   * Retorna o ID do membro com menor identificador do par.
   *
   * @return int
   *   ID do membro de menor identificador.
   */
  public function getMemberLowId(): int;

  /**
   * Retorna o ID do membro com maior identificador do par.
   *
   * @return int
   *   ID do membro de maior identificador.
   */
  public function getMemberHighId(): int;

  /**
   * Retorna o ID do usuário que enviou a solicitação.
   *
   * @return int
   *   ID do solicitante.
   */
  public function getRequesterId(): int;

  /**
   * Retorna o ID do usuário que recebeu a solicitação.
   *
   * @return int
   *   ID do destinatário.
   */
  public function getRecipientId(): int;

  /**
   * Retorna o estado atual do vínculo de amizade.
   *
   * @return string
   *   Estado do vínculo.
   */
  public function getStatus(): string;

}

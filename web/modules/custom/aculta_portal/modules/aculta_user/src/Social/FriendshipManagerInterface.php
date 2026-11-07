<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Social;

use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\user\UserInterface;

/**
 * Public API for member friendship requests. */
interface FriendshipManagerInterface {

  /**
   * Envia um pedido de amizade ao usuário informado.
   *
   * @param \Drupal\user\UserInterface $target
   *   Usuário que receberá o pedido.
   *
   * @return \Drupal\aculta_user\Entity\AcultaFriendshipInterface
   *   Vínculo de amizade criado pelo pedido.
   */
  public function request(UserInterface $target): AcultaFriendshipInterface;

  /**
   * Aceita um pedido de amizade pendente.
   *
   * @param \Drupal\aculta_user\Entity\AcultaFriendshipInterface $friendship
   *   Vínculo de amizade a aceitar.
   */
  public function accept(AcultaFriendshipInterface $friendship): void;

  /**
   * Recusa um pedido de amizade pendente.
   *
   * @param \Drupal\aculta_user\Entity\AcultaFriendshipInterface $friendship
   *   Vínculo de amizade a recusar.
   */
  public function decline(AcultaFriendshipInterface $friendship): void;

  /**
   * Cancela um pedido de amizade enviado.
   *
   * @param \Drupal\aculta_user\Entity\AcultaFriendshipInterface $friendship
   *   Vínculo de amizade a cancelar.
   */
  public function cancel(AcultaFriendshipInterface $friendship): void;

  /**
   * Remove uma amizade existente.
   *
   * @param \Drupal\aculta_user\Entity\AcultaFriendshipInterface $friendship
   *   Vínculo de amizade a remover.
   */
  public function remove(AcultaFriendshipInterface $friendship): void;

  /**
   * Indica se o usuário atual é amigo do usuário informado.
   *
   * @param int $targetUid
   *   ID do usuário alvo.
   *
   * @return bool
   *   TRUE se houver amizade confirmada.
   */
  public function areFriendsWith(int $targetUid): bool;

  /**
   * Retorna o vínculo de amizade com o usuário informado.
   *
   * @param int $targetUid
   *   ID do usuário alvo.
   *
   * @return \Drupal\aculta_user\Entity\AcultaFriendshipInterface|null
   *   Vínculo de amizade existente, ou NULL se não houver.
   */
  public function getRelationshipWith(int $targetUid): ?AcultaFriendshipInterface;

  /**
   * Retorna os pedidos de amizade recebidos pelo usuário atual.
   *
   * @return \Drupal\aculta_user\Entity\AcultaFriendshipInterface[]
   *   Pedidos de amizade pendentes.
   */
  public function getIncomingRequests(): array;

}

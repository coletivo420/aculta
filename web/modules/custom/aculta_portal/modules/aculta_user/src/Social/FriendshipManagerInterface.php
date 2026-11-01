<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Social;

use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\user\UserInterface;

/** Public API for member friendship requests. */
interface FriendshipManagerInterface {

  public function request(UserInterface $target): AcultaFriendshipInterface;

  public function accept(AcultaFriendshipInterface $friendship): void;

  public function decline(AcultaFriendshipInterface $friendship): void;

  public function cancel(AcultaFriendshipInterface $friendship): void;

  public function remove(AcultaFriendshipInterface $friendship): void;

  public function areFriendsWith(int $targetUid): bool;

  public function getRelationshipWith(int $targetUid): ?AcultaFriendshipInterface;

  /** @return \Drupal\aculta_user\Entity\AcultaFriendshipInterface[] */
  public function getIncomingRequests(): array;

}

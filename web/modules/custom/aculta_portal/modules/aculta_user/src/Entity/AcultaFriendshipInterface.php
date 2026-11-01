<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/** Stores one directional request and its state for a member pair. */
interface AcultaFriendshipInterface extends ContentEntityInterface {

  public function getMemberLowId(): int;

  public function getMemberHighId(): int;

  public function getRequesterId(): int;

  public function getRecipientId(): int;

  public function getStatus(): string;

}

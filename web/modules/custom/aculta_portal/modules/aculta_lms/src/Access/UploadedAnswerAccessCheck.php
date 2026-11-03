<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/** @deprecated Use AnswerAttachmentAccessCheck for ACULTA answer entities. */
final class UploadedAnswerAccessCheck {

  public function __construct(
    private readonly AnswerAttachmentAccessCheck $answerAccess,
  ) {}

  public function access(EntityInterface $answer, AccountInterface $account): AccessResultInterface {
    return $this->answerAccess->access($answer, $account);
  }

}

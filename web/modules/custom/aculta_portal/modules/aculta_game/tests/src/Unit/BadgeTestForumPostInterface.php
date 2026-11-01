<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_game\Unit;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Test double contract for a revisionable, owned ForumPost entity.
 */
interface BadgeTestForumPostInterface extends EntityPublishedInterface, RevisionableInterface, EntityOwnerInterface, FieldableEntityInterface {}

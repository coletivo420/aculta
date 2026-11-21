<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\Security;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Ensures route authorization is not the only guard before a POST mutation.
 */
#[Group('aculta_forum')]
final class CommentModerationFormBoundaryTest extends UnitTestCase {

  public function testFormReloadsEntityAndRechecksPolicyBeforeSaving(): void {
    $root = dirname(__DIR__, 4);
    $form = (string) file_get_contents($root . '/src/Form/DiscussionCommentModerationForm.php');
    $services = Yaml::parseFile($root . '/aculta_forum.services.yml', Yaml::PARSE_CUSTOM_TAGS);
    self::assertContains(
      '@aculta_forum.comment_moderation_access',
      $services['services']['Drupal\\aculta_forum\\Form\\DiscussionCommentModerationForm']['arguments'],
    );
    self::assertSame(
      ['@aculta_portal.domain_site', '@aculta_forum.discussion_origin_resolver', '@aculta_forum.discussion_context_resolver'],
      $services['services']['aculta_forum.comment_moderation_access']['arguments'],
    );
    self::assertStringContainsString("getStorage('comment')->loadUnchanged(\$cid)", $form);
    self::assertStringContainsString('modAccess->canModerate($fresh, $this->modModerator)', $form);
    self::assertStringContainsString('throw new AccessDeniedHttpException', $form);
    self::assertStringContainsString('modAccess->canModerate($comment, $this->modModerator)', $form);
  }

}

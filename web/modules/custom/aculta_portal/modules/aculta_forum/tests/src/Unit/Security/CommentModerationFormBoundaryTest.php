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
    $parsed = Yaml::parseFile($root . '/aculta_forum.services.yml', Yaml::PARSE_CUSTOM_TAGS);
    self::assertIsArray($parsed);
    $services = $parsed['services'] ?? NULL;
    self::assertIsArray($services);
    $formDefinition = $services['Drupal\\aculta_forum\\Form\\DiscussionCommentModerationForm'] ?? NULL;
    self::assertIsArray($formDefinition);
    $formArguments = $formDefinition['arguments'] ?? NULL;
    self::assertIsArray($formArguments);
    $accessDefinition = $services['aculta_forum.comment_moderation_access'] ?? NULL;
    self::assertIsArray($accessDefinition);
    $accessArguments = $accessDefinition['arguments'] ?? NULL;
    self::assertIsArray($accessArguments);
    self::assertContains('@aculta_forum.comment_moderation_access', $formArguments);
    self::assertSame([
      '@aculta_portal.domain_site',
      '@aculta_forum.discussion_origin_resolver',
      '@aculta_forum.discussion_context_resolver',
    ], $accessArguments);
    self::assertStringContainsString("getStorage('comment')->loadUnchanged(\$cid)", $form);
    self::assertStringContainsString('modAccess->canModerate($fresh, $this->modModerator)', $form);
    self::assertStringContainsString('throw new AccessDeniedHttpException', $form);
    self::assertStringContainsString('modAccess->canModerate($comment, $this->modModerator)', $form);
  }

}

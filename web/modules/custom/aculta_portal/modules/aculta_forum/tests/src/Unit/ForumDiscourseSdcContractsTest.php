<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Keeps Discourse-equivalent SDC metadata, stories and owners in sync.
 */
#[Group('aculta_forum')]
final class ForumDiscourseSdcContractsTest extends UnitTestCase {

  public function testAllAddedComponentsHaveStoriesAndStrictSchemas(): void {
    $directory = dirname(__DIR__, 8) . '/themes/custom/aculta420_v2/components';
    $components = [
      'forum-poll-card' => ['form'],
      'forum-poll-results' => [],
      'forum-revision-history' => ['revisions'],
      'forum-reading-timeline' => ['navigation'],
      'forum-draft-status' => ['actions'],
    ];
    foreach ($components as $name => $requiredSlots) {
      $path = $directory . '/' . $name;
      self::assertFileExists($path . '/' . $name . '.component.yml');
      $metadata = Yaml::parseFile($path . '/' . $name . '.component.yml');
      self::assertIsArray($metadata);
      self::assertSame('Comunidade', $metadata['group'] ?? NULL);
      self::assertSame(FALSE, $metadata['props']['additionalProperties'] ?? NULL);
      self::assertFileExists($path . '/' . $name . '.twig');
      self::assertFileExists($path . '/' . $name . '.css');
      foreach ($requiredSlots as $slot) {
        self::assertArrayHasKey($slot, $metadata['slots'] ?? [], $name);
      }
      $stories = glob($path . '/' . $name . '.*.story.yml');
      self::assertIsArray($stories);
      self::assertNotEmpty($stories, $name);
      foreach ($stories as $story) {
        $parsed = Yaml::parseFile($story);
        self::assertIsArray($parsed);
        self::assertSame('aculta420_v2:' . $name, $parsed['component'] ?? NULL);
      }
    }
  }

  public function testPollAndRevisionDisplayStayOwnedByDrupal(): void {
    $forum = dirname(__DIR__, 3);
    $vote = (string) file_get_contents($forum . '/src/Form/ForumPollVoteForm.php');
    $poll = (string) file_get_contents($forum . '/src/Hook/TopicPollRenderHooks.php');
    $revisions = (string) file_get_contents($forum . '/src/Controller/ForumPostRevisionHistoryController.php');
    self::assertStringContainsString("$results->resultsVisible", $vote);
    self::assertStringContainsString("'forum-poll-results'", $vote);
    self::assertStringContainsString("'forum-poll-card'", $poll);
    self::assertStringContainsString('ForumPollVoteForm::class', $poll);
    self::assertStringContainsString("'forum-revision-history'", $revisions);
    self::assertStringContainsString("'#slots' => ['revisions' => $historyTable]", $revisions);
    self::assertStringNotContainsString('->getStorage(', (string) file_get_contents(dirname($forum, 5) . '/themes/custom/aculta420_v2/components/forum-poll-results/forum-poll-results.twig'));
  }

}

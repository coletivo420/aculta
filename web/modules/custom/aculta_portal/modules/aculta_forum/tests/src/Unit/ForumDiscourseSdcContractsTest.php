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
      'forum-poll-builder' => ['toggle', 'fields'],
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
    self::assertStringContainsString('$results->resultsVisible', $vote);
    self::assertStringContainsString("'forum-poll-results'", $vote);
    self::assertStringContainsString("'forum-poll-card'", $poll);
    self::assertStringContainsString('ForumPollVoteForm::class', $poll);
    self::assertStringContainsString("'forum-revision-history'", $revisions);
    self::assertStringContainsString("'#slots' => ['revisions' =>", $revisions);
    self::assertStringNotContainsString('->getStorage(', (string) file_get_contents(dirname($forum, 5) . '/themes/custom/aculta420_v2/components/forum-poll-results/forum-poll-results.twig'));
  }

  public function testBuilderDefersSlotMappingUntilAfterFormProcessing(): void {
    $forum = dirname(__DIR__, 3);
    $composer = (string) file_get_contents($forum . '/src/Hook/TopicComposerFormHooks.php');
    self::assertStringContainsString("'#after_build' => [[self::class, 'afterBuildPollBuilder']]", $composer);
    $pending = [['existing', 'callback']];
    $after = \Drupal\aculta_forum\Hook\TopicComposerFormHooks::afterBuildPollBuilder(
      ['#pre_render' => $pending, 'poll_enabled' => ['#name' => 'poll_enabled']],
      $this->createMock(\Drupal\Core\Form\FormStateInterface::class),
    );
    self::assertCount(2, $after['#pre_render']);
    self::assertSame(['existing', 'callback'], $after['#pre_render'][0]);
    self::assertSame([\Drupal\aculta_forum\Hook\TopicComposerFormHooks::class, 'preRenderPollBuilder'], $after['#pre_render'][1]);
    self::assertSame('poll_enabled', $after['poll_enabled']['#name']);
    self::assertStringContainsString("':input[name=\"poll_enabled\"]'", $composer);
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    $builder = \Drupal\aculta_forum\Hook\TopicComposerFormHooks::preRenderPollBuilder([
      '#type' => 'container',
      '#pre_render' => [],
      'poll_enabled' => ['#type' => 'checkbox', '#name' => 'poll_enabled'],
      'poll' => ['#type' => 'container', 'poll_type' => ['#name' => 'poll_type']],
    ]);
    self::assertSame('portal_component', $builder['#theme']);
    self::assertSame('forum-poll-builder', $builder['#name']);
    self::assertSame('poll_enabled', $builder['#slots']['toggle']['#name']);
    self::assertSame('poll_type', $builder['#slots']['fields']['poll_type']['#name']);
    self::assertArrayNotHasKey('poll_enabled', $builder);
    self::assertArrayNotHasKey('poll', $builder);
  }

  public function testTimelineIsHistoryBackedAndClamped(): void {
    $timeline = \Drupal\aculta_forum\Hook\ForumReadingTimelineHooks::class;
    self::assertSame(0, $timeline::progressPercent(0, 10));
    self::assertSame(0, $timeline::progressPercent(10, 12));
    self::assertSame(60, $timeline::progressPercent(10, 4));
    self::assertSame(100, $timeline::progressPercent(10, -3));
    $forum = dirname(__DIR__, 3);
    $source = (string) file_get_contents($forum . '/src/Hook/ForumReadingTimelineHooks.php');
    self::assertStringContainsString("->getUnreadByTopics([\$topicId])", $source);
    self::assertStringContainsString("'fragment' => 'forum-post-' . (int) \$receipt['post_id']", $source);
    self::assertStringContainsString("'max-age' => 0", $source);
    self::assertStringContainsString("->accessCheck(TRUE)", $source);
  }

}

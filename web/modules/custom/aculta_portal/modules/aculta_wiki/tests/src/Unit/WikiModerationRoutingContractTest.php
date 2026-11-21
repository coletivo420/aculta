<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * No Wiki moderation route may reuse a Forum/Marley privilege boundary.
 */
#[Group('aculta_wiki')]
final class WikiModerationRoutingContractTest extends UnitTestCase {

  public function testWikiRoutesAndServiceBoundaries(): void {
    $root = dirname(__DIR__, 3);
    $raw = Yaml::parseFile($root . '/aculta_wiki.routing.yml');
    self::assertIsArray($raw);

    foreach ([
      'aculta_wiki.moderation_home' => 'overviewAccess',
      'aculta_wiki.moderation_proposals' => 'proposalsAccess',
      'aculta_wiki.moderation_revisions' => 'revisionsAccess',
      'aculta_wiki.moderation_reports' => 'reportsAccess',
      'aculta_wiki.moderation_report_decide' => 'reportAccess',
    ] as $route => $method) {
      $definition = $raw[$route] ?? NULL;
      self::assertIsArray($definition, $route);
      $requirements = $definition['requirements'] ?? NULL;
      self::assertIsArray($requirements);
      $options = $definition['options'] ?? NULL;
      self::assertIsArray($options);
      self::assertSame('aculta_wiki.moderation_access:' . $method, $requirements['_custom_access']);
      self::assertSame('wiki', $options['_aculta_site_key']);
      self::assertTrue($options['no_cache']);
      $path = $definition['path'] ?? NULL;
      self::assertIsString($path);
      self::assertStringStartsWith('/we-sativa/moderacao', $path);
      self::assertArrayNotHasKey('_admin_route', $options);
      self::assertStringNotContainsString('/marley', $path);
    }
    $decisionRoute = $raw['aculta_wiki.moderation_report_decide'] ?? NULL;
    self::assertIsArray($decisionRoute);
    $methods = $decisionRoute['methods'] ?? NULL;
    self::assertIsArray($methods);
    self::assertSame(['GET', 'HEAD', 'POST'], $methods);

    $parsed = Yaml::parseFile($root . '/aculta_wiki.services.yml', Yaml::PARSE_CUSTOM_TAGS);
    self::assertIsArray($parsed);
    $services = $parsed['services'] ?? NULL;
    self::assertIsArray($services);
    $acl = $services['aculta_wiki.moderation_access'] ?? NULL;
    self::assertIsArray($acl);
    self::assertSame('Drupal\\aculta_wiki\\Access\\WikiModerationAccess', $acl['class']);
    $controller = $services['aculta_wiki.moderation_controller'] ?? NULL;
    self::assertIsArray($controller);
    self::assertSame('Drupal\\aculta_wiki\\Controller\\WikiModerationController', $controller['class']);
  }

  public function testDecisionOwnershipAndPortalIsolation(): void {
    $wiki = dirname(__DIR__, 3);
    $root = dirname($wiki);
    $controller = (string) file_get_contents($wiki . '/src/Controller/WikiModerationController.php');
    $access = (string) file_get_contents($wiki . '/src/Access/WikiModerationAccess.php');
    $proposalForm = (string) file_get_contents($wiki . '/src/Form/WikiProposalDecisionForm.php');
    $cguForm = (string) file_get_contents($root . '/aculta_cgu/src/Form/CguReportDecisionForm.php');

    self::assertStringContainsString("getQueueItems(\$this->moderator, 'wiki'", $controller);
    self::assertStringContainsString("->condition('purpose', 'wiki')", $controller);
    self::assertStringContainsString('CguReportDecisionForm::class', $controller);
    self::assertStringContainsString("->get('purpose')->getString() !== 'wiki'", $access);
    self::assertStringContainsString("ModerationScope::allows(\$account, 'wiki')", $access);
    self::assertStringContainsString("setRedirect('aculta_wiki.moderation_proposals')", $proposalForm);
    self::assertStringContainsString("'aculta_wiki.moderation_report_decide' => 'aculta_wiki.moderation_reports'", $cguForm);
    self::assertStringNotContainsString('ForumMarleyTopicActions', $controller);
  }

  public function testWikiModerationSdcsHaveSchemasAndStories(): void {
    $root = dirname(__DIR__, 3);
    $themes = dirname($root, 5) . '/themes/custom/aculta420_v2/components';
    foreach ([
      'wiki-moderation-workspace' => 'wiki-moderation-workspace.default.story.yml',
      'wiki-moderation-case' => 'wiki-moderation-case.proposal.story.yml',
      'wiki-moderation-decision' => 'wiki-moderation-decision.default.story.yml',
    ] as $component => $story) {
      $directory = $themes . '/' . $component;
      $meta = Yaml::parseFile($directory . '/' . $component . '.component.yml');
      self::assertIsArray($meta);
      self::assertSame('Studio', $meta['group'] ?? NULL);
      self::assertFileExists($directory . '/' . $component . '.twig');
      self::assertFileExists($directory . '/' . $component . '.css');
      self::assertFileExists($directory . '/' . $story);
      $storyMeta = Yaml::parseFile($directory . '/' . $story);
      self::assertIsArray($storyMeta);
    }
  }

}

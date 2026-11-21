<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\ForumMarleyTopicActions;
use Drupal\aculta_forum\Service\TopicAuthorDeletion;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Yaml\Yaml;

/**
 * Checks Forum-owned access controls on Marley topic commands.
 */
#[Group('aculta_forum')]
final class ForumMarleyTopicActionsTest extends UnitTestCase {

  public function testModeratorWithOriginAccessCanManageForumTopic(): void {
    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->method('getSiteKeyValueForDomainId')->with('domain-forum')->willReturn('forum');
    $sites->method('runInSite')->with('forum')->willReturnCallback(static fn (string $site, callable $callback): mixed => $callback());
    $account = $this->account(TRUE);
    $topic = $this->topic('aculta_forum_topic');
    $topic->method('access')->willReturn(AccessResult::allowed());

    $manager = new ForumMarleyTopicActions($sites, $this->createMock(TopicAuthorDeletion::class));

    self::assertTrue($manager->access($account, $topic)->isAllowed());
  }

  public function testOtherRolesAndBundlesAreDenied(): void {
    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->method('getSiteKeyValueForDomainId')->willReturn('forum');
    $manager = new ForumMarleyTopicActions($sites, $this->createMock(TopicAuthorDeletion::class));

    self::assertFalse($manager->access($this->account(FALSE), $this->topic('aculta_forum_topic'))->isAllowed());
    self::assertFalse($manager->access($this->account(TRUE), $this->topic('revista'))->isAllowed());
    self::assertFalse($manager->access($this->account(TRUE), NULL)->isAllowed());
  }

  public function testWrongDomainIsDenied(): void {
    $sites = $this->createMock(DomainSiteManagerInterface::class);
    $sites->method('getSiteKeyValueForDomainId')->willReturn('courses');
    $manager = new ForumMarleyTopicActions($sites, $this->createMock(TopicAuthorDeletion::class));

    self::assertFalse($manager->access($this->account(TRUE), $this->topic('aculta_forum_topic'))->isAllowed());
  }

  public function testRoutesUseOwnerFormsAndNeverAdminPaths(): void {
    $root = dirname(__DIR__, 3);
    $routes = Yaml::parseFile($root . '/aculta_forum.routing.yml');
    self::assertIsArray($routes);
    foreach ([
      'aculta_forum.marley_topic_publish' => 'TopicModerationPublishForm',
      'aculta_forum.marley_topic_close' => 'TopicModerationCloseForm',
      'aculta_forum.marley_topic_pin' => 'TopicModerationPinForm',
      'aculta_forum.marley_topic_archive' => 'TopicModerationArchiveForm',
    ] as $name => $form) {
      $route = $routes[$name] ?? [];
      self::assertIsArray($route);
      $defaults = $route['defaults'] ?? [];
      self::assertIsArray($defaults);
      $formClass = $defaults['_form'] ?? '';
      self::assertIsString($formClass);
      self::assertStringContainsString($form, $formClass);
      $options = $route['options'] ?? [];
      self::assertIsArray($options);
      self::assertSame('account', $options['_aculta_site_key'] ?? NULL);
      $parameters = $options['parameters'] ?? [];
      self::assertIsArray($parameters);
      $node = $parameters['node'] ?? [];
      self::assertIsArray($node);
      self::assertSame('entity:node', $node['type'] ?? NULL);
      self::assertContains('POST', is_array($route['methods'] ?? NULL) ? $route['methods'] : []);
      $path = $route['path'] ?? '';
      self::assertIsString($path);
      self::assertStringStartsWith('/marley/forum/topico/', $path);
      self::assertFalse($options['_admin_route'] ?? FALSE);
      $requirements = $route['requirements'] ?? [];
      self::assertIsArray($requirements);
      self::assertSame('aculta_forum.marley_topic_actions:access', $requirements['_custom_access'] ?? NULL);
    }

    $source = (string) file_get_contents($root . '/src/Service/ForumMarleyTopicActions.php');
    self::assertStringContainsString("hasPermission('moderate aculta forum topics')", $source);
    self::assertStringContainsString("runInSite('forum'", $source);
    self::assertStringNotContainsString('/painel-administrativo/', $source);
    foreach (['TopicModerationCloseForm', 'TopicModerationPinForm', 'TopicModerationArchiveForm'] as $form) {
      $code = (string) file_get_contents($root . '/src/Form/' . $form . '.php');
      self::assertStringContainsString("'aculta_cgu.forum_marley_queue'", $code);
    }
  }

  private function topic(string $bundle): MockObject&NodeInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('__get')->with('target_id')->willReturn('domain-forum');
    $node = $this->createMock(NodeInterface::class);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheTags')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);
    $node->method('bundle')->willReturn($bundle);
    $node->method('hasField')->willReturn(TRUE);
    $node->method('get')->willReturn($field);
    return $node;
  }

  private function account(bool $grant): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => $grant && $permission === 'moderate aculta forum topics',
    );
    return $account;
  }

}

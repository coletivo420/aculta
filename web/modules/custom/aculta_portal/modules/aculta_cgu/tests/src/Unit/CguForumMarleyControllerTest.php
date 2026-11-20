<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Unit;

use Drupal\aculta_cgu\Controller\CguForumMarleyController;
use Drupal\aculta_cgu\Service\CguReportQueue;
use Drupal\aculta_cgu\Service\ReportService;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards the moderation frontend purpose and route separation.
 */
#[Group('aculta_cgu')]
final class CguForumMarleyControllerTest extends UnitTestCase {

  /**
   * A scoped Forum moderator may use the queue without global CGU grants.
   */
  public function testForumModeratorAccess(): void {
    $account = $this->account(['moderar denuncias do forum']);
    self::assertTrue($this->controller()->access($account)->isAllowed());
    self::assertFalse($this->controller()->reportAccess($account, NULL)->isAllowed());
  }

  /**
   * Neither anonymous users nor editors of other purposes may enter.
   */
  public function testOtherPurposesAreDenied(): void {
    self::assertFalse($this->controller()->access($this->account(['moderar denuncias cgu: revista']))->isAllowed());
    self::assertFalse($this->controller()->access($this->account([]))->isAllowed());
    self::assertFalse($this->controller()->access($this->account(['moderar denuncias cgu: forum'], TRUE))->isAllowed());
  }

  /**
   * The frontend uses scoped routes and real owner services.
   */
  public function testFrontendRoutesAreNotAdministrative(): void {
    $root = dirname(__DIR__, 3);
    $routes = Yaml::parseFile($root . '/aculta_cgu.routing.yml');
    foreach (['aculta_cgu.forum_marley_queue', 'aculta_cgu.forum_marley_decide'] as $route) {
      self::assertArrayHasKey($route, $routes);
      self::assertSame('account', $routes[$route]['options']['_aculta_site_key']);
      self::assertTrue($routes[$route]['options']['no_cache']);
      self::assertFalse($routes[$route]['options']['_admin_route'] ?? FALSE);
      self::assertStringStartsWith('/marley/forum/moderacao/', $routes[$route]['path']);
      self::assertStringContainsString('reportAccess', $routes['aculta_cgu.forum_marley_decide']['requirements']['_custom_access']);
    }
    self::assertContains('POST', $routes['aculta_cgu.forum_marley_decide']['methods']);
    self::assertSame('entity:aculta_cgu_report', $routes['aculta_cgu.forum_marley_decide']['options']['parameters']['aculta_cgu_report']['type']);

    $studioTab = (string) file_get_contents(dirname($root) . '/aculta_studio/src/Plugin/MarleyTab/ForumTab.php');
    self::assertStringContainsString("'aculta_cgu.forum_marley_queue'", $studioTab);
    self::assertStringNotContainsString("'aculta_user.denuncias_forum'", $studioTab);
    self::assertStringContainsString("ModerationScope::allows(\$account, 'forum')", $studioTab);
  }

  /**
   * The CGU form stays the sole owner of POST+CSRF and decision transitions.
   */
  public function testDecisionFormReusesCguServiceAndFrontendReturn(): void {
    $root = dirname(__DIR__, 3);
    $controller = (string) file_get_contents($root . '/src/Controller/CguForumMarleyController.php');
    $form = (string) file_get_contents($root . '/src/Form/CguReportDecisionForm.php');
    self::assertStringContainsString("'forum'", $controller);
    self::assertStringContainsString("->access('view', \$account, TRUE)", $controller);
    self::assertStringContainsString('CguReportDecisionForm::class', $controller);
    self::assertStringContainsString('->resolvePurpose($entity)', $controller);
    self::assertStringContainsString('->decideReport(', $form);
    $service = (string) file_get_contents($root . '/src/Service/ReportService.php');
    self::assertStringContainsString('->loadUnchanged($reportId)', $service);
    self::assertStringContainsString('aculta_cgu:report_decision:', $service);
    self::assertStringContainsString('->release($lockName)', $service);
    self::assertStringContainsString("=== 'aculta_cgu.forum_marley_decide'", $form);
    self::assertStringContainsString("'aculta_cgu.forum_marley_queue'", $form);
    self::assertStringNotContainsString('/painel-administrativo/', $controller);
  }

  /**
   * Builds the controller with only mocked collaborators (no Runtime).
   */
  private function controller(): CguForumMarleyController {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    return new CguForumMarleyController(
      new CguReportQueue($entities),
      $this->createMock(ReportService::class),
      $entities,
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(FormBuilderInterface::class),
    );
  }

  /**
   * Creates an account with scoped permission grants.
   *
   * @param string[] $permissions
   *   Permissões da conta.
   */
  private function account(array $permissions, bool $anonymous = FALSE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(!$anonymous);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, $permissions, TRUE),
    );
    return $account;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Kernel;

use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_wiki\Event\WikiEntryReviewRequestedEvent;
use Drupal\aculta_wiki\Service\WikiEditorialEventQueue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies the real Wiki queue service and transactional persistence.
 */
#[Group('aculta_wiki')]
#[RunTestsInSeparateProcesses]
final class WikiEditorialEventQueueKernelTest extends KernelTestBase {

  /**
   * Enabled dependency modules for this Kernel boot.
   *
   * @var list<string>
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected function bootKernel(): void {
    static::$modules = $this->moduleDependencyClosure(['aculta_wiki', 'commerce_cart']);
    parent::bootKernel();
  }

  /**
   * Persists items and rolls them back with the active database transaction. */
  public function testRealServicePersistsAndRollsBackQueueItems(): void {
    $service = $this->container->get('aculta_wiki.editorial_event_queue');
    self::assertInstanceOf(WikiEditorialEventQueue::class, $service);
    $service->prepareStorage();
    $queue = $this->container->get('queue')->get(WikiEditorialEventQueue::QUEUE_ID, TRUE);
    self::assertTrue($this->container->get('database')->schema()->tableExists('queue'));
    $event = new WikiEntryReviewRequestedEvent('b4a5e745-63b0-4f5d-94a1-57b25283db0e', 18);

    $transaction = $this->container->get('database')->startTransaction();
    $service->enqueue($event);
    self::assertSame(1, $queue->numberOfItems());
    $transaction->rollBack();
    self::assertSame(0, $queue->numberOfItems());

    $service->enqueue($event);
    self::assertSame(1, $queue->numberOfItems());
    $item = $queue->claimItem();
    self::assertNotFalse($item);
    self::assertSame($event->toQueuePayload(), $item->data);
    $queue->deleteItem($item);
    self::assertSame(0, $queue->numberOfItems());
  }

  /**
   * Resolves only declared dependencies, keeping Studio outside this boot.
   *
   * @param list<string> $roots
   *   Root modules to enable.
   *
   * @return list<string>
   *   Dependency-ordered extension names.
   */
  private function moduleDependencyClosure(array $roots): array {
    $discovery = new ExtensionDiscovery(DRUPAL_ROOT);
    $discovery->setProfileDirectories([]);
    $extensions = $discovery->scan('module');
    $ordered = [];
    $visited = [];
    $visit = function (string $module) use (&$visit, &$ordered, &$visited, $extensions): void {
      if (isset($visited[$module])) {
        return;
      }
      if (!isset($extensions[$module])) {
        throw new \RuntimeException(sprintf('Required module %s is unavailable.', $module));
      }
      $info = Yaml::parseFile(DRUPAL_ROOT . '/' . $extensions[$module]->getPathname());
      foreach ($info['dependencies'] ?? [] as $dependency) {
        if (!is_string($dependency)) {
          continue;
        }
        $dependency = substr($dependency, strrpos($dependency, ':') === FALSE ? 0 : strrpos($dependency, ':') + 1);
        if (isset($extensions[$dependency])) {
          $visit($dependency);
        }
      }
      $visited[$module] = TRUE;
      $ordered[] = $module;
    };
    foreach ($roots as $root) {
      $visit($root);
    }
    return $ordered;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\aculta_messenger\Aviso\PersonalNoticePageState;
use Drupal\aculta_messenger\Controller\MessagesController;
use Drupal\flag\Entity\Storage\FlaggingStorageInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Verifies persistent notices paginate only after access and read filtering.
 */
#[CoversClass(MessagesController::class)]
#[Group('aculta_messenger')]
final class PersistentNoticePaginationTest extends UnitTestCase {

  /**
   * Access-revoked and read rows do not consume page offsets or capacity.
   */
  public function testPageOffsetCountsOnlyAccessibleUnreadNotices(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $flag = $this->createMock(FlagInterface::class);
    $flag->method('id')->willReturn('aculta_notice_read');
    $flagService = $this->createMock(FlagServiceInterface::class);
    $flagService->method('getFlagById')->with('aculta_notice_read')->willReturn($flag);

    $flaggingStorage = $this->createMock(FlaggingStorageInterface::class);
    $flaggingStorage->expects(self::once())
      ->method('loadIsFlaggedMultiple')
      ->willReturn([
        4 => ['aculta_notice_read' => TRUE],
        5 => ['aculta_notice_read' => TRUE],
      ]);
    $definition = $this->createMock(EntityTypeInterface::class);
    $definition->method('getListCacheTags')->willReturn(['flagging_list']);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $query = $this->createMock(QueryInterface::class);
    $query->expects(self::once())->method('accessCheck')->with(TRUE)->willReturnSelf();
    $query->expects(self::exactly(2))->method('condition')->willReturnSelf();
    $query->expects(self::exactly(2))->method('sort')->willReturnSelf();
    $query->expects(self::once())->method('range')->with(0, 20)->willReturnSelf();
    $query->expects(self::once())->method('execute')->willReturn(range(1, 20));
    $nodeStorage->expects(self::once())->method('getQuery')->willReturn($query);
    $nodeStorage->expects(self::once())
      ->method('loadMultiple')
      ->willReturn($this->notices(range(1, 20), $account));
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static function (string $type) use ($nodeStorage, $flaggingStorage): EntityStorageInterface {
      return $type === 'node' ? $nodeStorage : $flaggingStorage;
    });
    $entityTypeManager->method('getDefinition')->with('flagging')->willReturn($definition);
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(static fn(string $message): string => $message);

    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->method('generateFromRoute')->willReturnCallback(static function (string $name, array $parameters = [], array $options = [], bool $collectMetadata = FALSE): string|GeneratedUrl {
      return $collectMetadata
        ? (new GeneratedUrl())->setGeneratedUrl('/comunicado/' . (int) ($parameters['node'] ?? 0))
        : '/comunicado/' . (int) ($parameters['node'] ?? 0);
    });
    $originalContainer = $this->currentDrupalContainer();
    $container = new ContainerBuilder();
    $container->set('url_generator', $urlGenerator);
    $container->set('string_translation', $translation);
    \Drupal::setContainer($container);

    try {
      $controller = (new \ReflectionClass(MessagesController::class))->newInstanceWithoutConstructor();
      $this->setProperty($controller, 'account', $account);
      $this->setProperty($controller, 'flagService', $flagService);
      $this->setProperty($controller, 'entityTypes', $entityTypeManager);
      $this->setProperty($controller, 'translation', $translation);
      $pageState = new PersonalNoticePageState('comunicado', 2);

      $method = new \ReflectionMethod(MessagesController::class, 'buildPersistentNoticeItems');
      $items = $method->invoke(
        $controller,
        2,
        new CacheableMetadata(),
        ['origem' => 'comunicado', 'leitura' => 'unread'],
        $pageState,
      );

      self::assertCount(2, $items);
      self::assertSame('Não lido', $items[0]['link']['#value']['state']['#value']);
      self::assertSame('Não lido', $items[1]['link']['#value']['state']['#value']);
      self::assertSame('/comunicado/8', (string) $items[0]['link']['#element_attributes']->offsetGet('href'));
      self::assertSame('/comunicado/9', (string) $items[1]['link']['#element_attributes']->offsetGet('href'));
      self::assertTrue($pageState->hasNext());
    }
    finally {
      if ($originalContainer instanceof ContainerInterface) {
        \Drupal::setContainer($originalContainer);
      }
      else {
        \Drupal::unsetContainer();
      }
    }
  }

  /**
   * Creates notices with three revoked rows and two already-read rows.
   *
   * @param int[] $ids
   *   Node IDs to create.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account whose current view permission is revalidated.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Test nodes keyed by entity ID.
   */
  private function notices(array $ids, AccountInterface $account): array {
    $nodes = [];
    foreach ($ids as $id) {
      $node = $this->createMock(NodeInterface::class);
      $node->method('id')->willReturn($id);
      $node->method('label')->willReturn('Notice ' . $id);
      $node->method('access')->with('view', $account, TRUE)->willReturn($id <= 3 ? AccessResult::forbidden() : AccessResult::allowed());
      $node->method('hasField')->willReturn(FALSE);
      $node->method('getCacheContexts')->willReturn([]);
      $node->method('getCacheTags')->willReturn([]);
      $node->method('getCacheMaxAge')->willReturn(0);
      $nodes[$id] = $node;
    }
    return $nodes;
  }

  /**
   * Saves one private controller dependency for the focused helper test.
   */
  private function setProperty(MessagesController $controller, string $property, object $value): void {
    (new \ReflectionProperty(MessagesController::class, $property))->setValue($controller, $value);
  }

  /**
   * Returns the existing Drupal container when the Unit harness has one.
   */
  private function currentDrupalContainer(): ?ContainerInterface {
    try {
      return \Drupal::getContainer();
    }
    catch (\Throwable) {
      return NULL;
    }
  }

}

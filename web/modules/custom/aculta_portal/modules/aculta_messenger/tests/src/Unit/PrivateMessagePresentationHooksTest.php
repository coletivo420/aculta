<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Hook\PrivateMessagePresentationHooks;
use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\ImageRenderer;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the render contract for the private message notification block.
 */
#[Group('aculta_messenger')]
final class PrivateMessagePresentationHooksTest extends TestCase {

  /**
   * The notification link uses a render array and caps large unread counts.
   */
  public function testNotificationBlockVariablesArePrepared(): void {
    $translation = $this->createMock(TranslationInterface::class);
    $linkTitle = new TranslatableMarkup('Unread messages: @count', ['@count' => 101]);
    $translation->expects(self::once())
      ->method('formatPlural')
      ->with(101, 'You have 1 unread private message', 'You have @count unread private messages')
      ->willReturn($linkTitle);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects(self::once())
      ->method('getModule')
      ->with('aculta_messenger')
      ->willReturn(new Extension(
        DRUPAL_ROOT,
        'module',
        'modules/custom/aculta_portal/modules/aculta_messenger/aculta_messenger.info.yml',
      ));

    $requests = new RequestStack();
    $request = Request::create('/subdir/index.php');
    $request->server->set('SCRIPT_NAME', '/subdir/index.php');
    $request->server->set('SCRIPT_FILENAME', '/var/www/index.php');
    $requests->push($request);
    $hooks = new PrivateMessagePresentationHooks(
      $this->createMock(AccountInterface::class),
      new AvatarResolver(new ImageRenderer(new ImageCatalog(), $this->createMock(EntityTypeManagerInterface::class))),
      $moduleHandler,
      $translation,
      $requests,
    );

    $variables = [
      'new_items_count' => 101,
      'count_method' => 'threads',
    ];
    $hooks->preprocessNotificationBlock($variables);

    self::assertSame('/subdir/modules/custom/aculta_portal/modules/aculta_messenger/images/private-message-notification-icon.png', $variables['notification_image_path']);
    self::assertSame('unread-threads', $variables['unread_notification_class']);
    $link = $variables['private_message_page_link'] ?? NULL;
    self::assertIsArray($link);
    self::assertSame('link', $link['#type'] ?? NULL);
    self::assertSame('99+', $link['#title'] ?? NULL);
    $url = $link['#url'] ?? NULL;
    self::assertInstanceOf(Url::class, $url);
    self::assertSame('private_message.private_message_page', $url->getRouteName());
    $attributes = $url->getOptions()['attributes'] ?? NULL;
    self::assertIsArray($attributes);
    self::assertSame($linkTitle, $attributes['aria-label'] ?? NULL);
    self::assertSame($linkTitle, $attributes['title'] ?? NULL);
  }

}

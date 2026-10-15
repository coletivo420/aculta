<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Controller\MessagesController;
use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message\MessageInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\site_alert\GetAlertsInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Asserts private notification listings remain bound to the session owner.
 *
 * @group aculta_messenger
 */
#[Group('aculta_messenger')]
final class PrivateNotificationOwnershipTest extends UnitTestCase {

  public function testQueryIsBoundToSessionUidAndMismatchedEntityIsDiscarded(): void {
    $conditions = [];
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(FALSE)->willReturnSelf();
    $query->method('condition')->willReturnCallback(static function (string $field, mixed $value, ?string $operator = NULL) use (&$conditions, $query): QueryInterface {
      $conditions[] = [$field, $value, $operator];
      return $query;
    });
    $query->method('sort')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn([9]);

    $message = $this->createMock(MessageInterface::class);
    $message->method('getOwnerId')->willReturn(77);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->with([9])->willReturn([9 => $message]);

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getListCacheTags')->willReturn(['message_list']);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->with('message')->willReturn($storage);
    $entityTypes->method('getDefinition')->with('message')->willReturn($entityType);

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('42');
    $controller = new MessagesController(
      $this->domainPurposeManager($entityTypes),
      $entityTypes,
      $account,
      $this->createMock(PrivateMessageServiceInterface::class),
      new UnreadThreadCounter($account, $this->createMock(PrivateMessageServiceInterface::class)),
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(GetAlertsInterface::class),
      $this->createMock(FlagServiceInterface::class),
      $this->translation(),
      $this->createMock(FormBuilderInterface::class),
      $this->createMock(RendererInterface::class),
      $this->createMock(DateFormatterInterface::class),
    );

    $build = $controller->notifications();

    $this->assertContains(['uid', 42, NULL], $conditions);
    $this->assertContains(['template', 'aculta_msg_private_notification', NULL], $conditions);
    $this->assertContains(['field_aculta_msg_delivery', 'sent', NULL], $conditions);
    $this->assertSame('Você ainda não recebeu notificações de mensagens.', $build['messages']['#items'][0]['#plain_text']);
  }

  private function domainPurposeManager(EntityTypeManagerInterface $entityTypes): DomainPurposeManager {
    return new DomainPurposeManager(
      $entityTypes,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

  private function translation(): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(static fn (string $message): string => $message);
    return $translation;
  }

}

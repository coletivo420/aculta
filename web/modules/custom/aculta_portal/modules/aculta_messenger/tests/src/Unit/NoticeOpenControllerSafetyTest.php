<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Integration\Commerce\CommerceOrderNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Commerce\Controller\CommerceOrderNoticeOpenController;
use Drupal\aculta_messenger\Integration\Donation\Controller\DonationNoticeOpenController;
use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Lms\Controller\LearningNoticeOpenController;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Wiki\Controller\WikiNoticeOpenController;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Ensures automatic requests do not mark source notices as read.
 */
#[Group('aculta_messenger')]
final class NoticeOpenControllerSafetyTest extends UnitTestCase {

  /**
   * A normal same-site navigation keeps the existing mark-as-read behavior.
   */
  public function testMemberNavigationMarksNoticesRead(): void {
    $account = $this->account();
    $request = $this->requestStack('');
    $message = $this->ownedMessage();

    $lms = $this->createMock(LearningNoticeManagerInterface::class);
    $lms->method('resolve')->willReturn(['url' => $this->targetUrl()]);
    $lms->expects(self::once())->method('markRead')->with($message);
    $lmsResponse = (new LearningNoticeOpenController($lms, $account, $request))->open($message);
    self::assertSame(302, $lmsResponse->getStatusCode());

    $wiki = $this->createMock(WikiNoticeManagerInterface::class);
    $wiki->method('resolve')->willReturn(['url' => $this->targetUrl()]);
    $wikiRead = $this->field(FALSE);
    $wikiMessage = $this->ownedMessageWithField('field_aculta_wiki_read', $wikiRead);
    $wikiMessage->expects(self::once())->method('set')->with('field_aculta_wiki_read', 1)->willReturnSelf();
    $wikiMessage->expects(self::once())->method('save')->willReturn(1);
    $wikiResponse = (new WikiNoticeOpenController($wiki, $account, $request))->open($wikiMessage);
    self::assertSame(302, $wikiResponse->getStatusCode());

    $commerce = $this->createMock(CommerceOrderNoticeManagerInterface::class);
    $commerce->method('resolve')->willReturn(['url' => $this->targetUrl()]);
    $commerceRead = $this->field(FALSE);
    $commerceMessage = $this->ownedMessageWithField('field_aculta_commerce_read', $commerceRead);
    $commerceMessage->expects(self::once())->method('set')->with('field_aculta_commerce_read', 1)->willReturnSelf();
    $commerceMessage->expects(self::once())->method('save')->willReturn(1);
    $commerceResponse = (new CommerceOrderNoticeOpenController($commerce, $account, $request))->open($commerceMessage);
    self::assertSame(302, $commerceResponse->getStatusCode());

    $donation = $this->createMock(DonationNoticeManagerInterface::class);
    $donation->method('resolve')->willReturn(['url' => $this->targetUrl()]);
    $donation->expects(self::once())->method('markRead')->with(self::anything(), $account)->willReturn(TRUE);
    $donationResponse = (new DonationNoticeOpenController($donation, $account, $request))->open($this->ownedMessage());
    self::assertSame(302, $donationResponse->getStatusCode());
  }

  /**
   * LMS prefetch preserves the unread state.
   */
  public function testLmsPrefetchDoesNotMarkRead(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->method('resolve')->willReturn(['url' => $this->targetUrl()]);
    $manager->expects(self::never())->method('markRead');
    $response = (new LearningNoticeOpenController($manager, $this->account(), $this->requestStack('prefetch')))
      ->open($this->ownedMessage());
    self::assertSame(204, $response->getStatusCode());
  }

  /**
   * Wiki cross-site navigation preserves the unread state.
   */
  public function testWikiCrossSiteRequestDoesNotMarkRead(): void {
    $manager = $this->createMock(WikiNoticeManagerInterface::class);
    $manager->method('resolve')->willReturn(['url' => $this->targetUrl()]);
    $manager->expects(self::never())->method('setRead');
    $message = $this->ownedMessage();
    $message->expects(self::never())->method('get');
    $response = (new WikiNoticeOpenController($manager, $this->account(), $this->requestStack('cross-site')))
      ->open($message);
    self::assertSame(302, $response->getStatusCode());
  }

  /**
   * Commerce prefetch preserves the unread state.
   */
  public function testCommercePrefetchDoesNotMarkRead(): void {
    $manager = $this->createMock(CommerceOrderNoticeManagerInterface::class);
    $manager->method('resolve')->willReturn(['url' => $this->targetUrl()]);
    $manager->expects(self::never())->method('setRead');
    $message = $this->ownedMessage();
    $message->expects(self::never())->method('get');
    $response = (new CommerceOrderNoticeOpenController($manager, $this->account(), $this->requestStack('prefetch')))
      ->open($message);
    self::assertSame(204, $response->getStatusCode());
  }

  /**
   * Donation prerender preserves the unread state.
   */
  public function testDonationPrerenderDoesNotMarkRead(): void {
    $manager = $this->createMock(DonationNoticeManagerInterface::class);
    $manager->method('resolve')->willReturn(['url' => $this->targetUrl()]);
    $manager->expects(self::never())->method('markRead');
    $response = (new DonationNoticeOpenController($manager, $this->account(), $this->requestStack('prerender')))
      ->open($this->ownedMessage());
    self::assertSame(204, $response->getStatusCode());
  }

  /**
   * Creates an owned Message fixture.
   */
  private function ownedMessage(): MessageInterface {
    $message = $this->createMock(MessageInterface::class);
    $message->method('getOwnerId')->willReturn(42);
    return $message;
  }

  /**
   * Creates an owned Message fixture with one field value.
   */
  private function ownedMessageWithField(string $fieldName, FieldItemListInterface $field): MessageInterface {
    $message = $this->ownedMessage();
    $message->method('get')->with($fieldName)->willReturn($field);
    return $message;
  }

  /**
   * Creates a scalar field item fixture.
   */
  private function field(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

  /**
   * Creates the current account fixture.
   */
  private function account(): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('42');
    return $account;
  }

  /**
   * Creates the browser request for a route invocation.
   */
  private function requestStack(string $purpose): RequestStack {
    $request = Request::create('/avisos/mensagem/abrir');
    if ($purpose === 'cross-site') {
      $request->headers->set('Sec-Fetch-Site', 'cross-site');
    }
    elseif ($purpose !== '') {
      $request->headers->set('Purpose', $purpose);
    }
    $stack = new RequestStack();
    $stack->push($request);
    return $stack;
  }

  /**
   * Provides a safe target URL for a validated notice.
   */
  private function targetUrl(): Url {
    $url = $this->getMockBuilder(Url::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['toString'])
      ->getMock();
    $url->method('toString')->willReturn('https://shop.example.test/order');
    return $url;
  }

}

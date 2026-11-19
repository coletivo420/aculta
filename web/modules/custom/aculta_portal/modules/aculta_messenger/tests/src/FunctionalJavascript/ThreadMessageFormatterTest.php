<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\aculta_messenger\Traits\PrivateMessageTestTrait;
use Drupal\Tests\aculta_messenger\Traits\ThreadMessageFormatterTestTrait;
use Drupal\aculta_messenger\Entity\PrivateMessage;

/**
 * JS tests for Thread Message Formatter.
 *
 * @group private_message
 */
class ThreadMessageFormatterTest extends WebDriverTestBase {

  use PrivateMessageTestTrait;
  use ThreadMessageFormatterTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['aculta_messenger'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->createTestingUsers();
  }

  /**
   * Tests load previous functionality.
   *
   * @param string $order
   *   Ordem das mensagens.
   * @param bool $reverse
   *   Se a ordem é invertida.
   * @param array<int, int> $offset
   *   Deslocamentos de paginação.
   *
   * @dataProvider loadPreviousProvider
   */
  public function testLoadPrevious(string $order, bool $reverse, array $offset): void {
    $thread = $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['b'],
    ], $this->users['b'], 5);

    $this->updateWidgetSettings([
      'message_count' => 2,
      'ajax_previous_load_count' => 2,
      'message_order' => $order,
    ]);

    $messageIdsForThread = $this->getMessageIdsForThread($thread);
    if ($reverse) {
      $messageIdsForThread = array_reverse($messageIdsForThread);
    }

    $this->drupalLogin($this->users['a']);
    $this->drupalGet('private-messages');

    $this->assertEquals(
      array_slice($messageIdsForThread, $offset[0], 2),
      $this->getMessageIdsFromMarkup(),
      'Threads are not in the expected order.'
    );

    $this->getSession()
      ->getPage()
      ->clickLink('Load Previous');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertEquals(
      array_slice($messageIdsForThread, $offset[1], 4),
      $this->getMessageIdsFromMarkup(),
      'Threads are not in the expected order.'
    );

    $this->assertEquals(4, $this->countMessages());

    $this->getSession()
      ->getPage()
      ->clickLink('Load Previous');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertEquals(5, $this->countMessages());
    $this->assertEquals(
      $messageIdsForThread,
      $this->getMessageIdsFromMarkup(),
      'Threads are not in the expected order.'
    );

    $this->assertSession()
      ->pageTextNotContains('Load Previous');
  }

  /**
   * Data for the testLoadPrevious().
   */
  public static function loadPreviousProvider(): array {
    return [
      'Ascending' => ['asc', FALSE, [-2, -4]],
      'Descending' => ['desc', TRUE, [0, 0]],
    ];
  }

  /**
   * Tests load new message.
   */
  public function testLoadNew(): void {
    $thread = $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['b'],
    ], $this->users['b'], 2);
    $this->updateWidgetSettings([
      'ajax_refresh_rate' => 1,
    ]);

    $this->drupalLogin($this->users['a']);
    $this->drupalGet('private-messages');
    $this->assertEquals(2, $this->countMessages());

    // Add a new message in background.
    $helloTest = 'Hello, user!';
    $message = PrivateMessage::create([
      'owner' => $this->users['b'],
      'message' => [
        'value' => $helloTest,
        'format' => 'plain_text',
      ],
    ]);
    $message->save();
    $thread->addMessage($message)->save();

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals(3, $this->countMessages());
    $this->assertSession()
      ->pageTextContains($helloTest);
  }

  /**
   * Tests submission after message deletion.
   */
  public function testSubmissionAfterMessageDeletion(): void {
    $thread = $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['b'],
    ], $this->users['b'], 2);

    $this->drupalLogin($this->users['a']);
    $this->drupalGet('private-messages');

    // Delete a previous message.
    $messages = $thread->getMessages();
    /** @var \Drupal\aculta_messenger\Entity\PrivateMessageInterface $firstMessage */
    $firstMessage = reset($messages);
    $firstMessage->delete();

    $helloTest = 'Hello, user!';
    $this->getSession()->getPage()->fillField('Message', $helloTest);
    $this->getSession()->getPage()->pressButton('Send');

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()
      ->pageTextContains($helloTest);
  }

  /**
   * Tests load more after submission.
   */
  public function testLoadMoreAfterSubmission(): void {
    $this->createThreadWithMessages([
      $this->users['a'],
      $this->users['b'],
    ], $this->users['b'], 2);

    $this->updateWidgetSettings([
      'message_count' => 1,
      'ajax_previous_load_count' => 1,
    ]);

    $this->drupalLogin($this->users['a']);
    $this->drupalGet('private-messages');

    $helloTest = 'Hello, user!';
    $this->getSession()->getPage()->fillField('Message', $helloTest);
    $this->getSession()->getPage()->pressButton('Send');

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals(2, $this->countMessages());

    $this->getSession()
      ->getPage()
      ->clickLink('Load Previous');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertEquals(3, $this->countMessages());
  }

}

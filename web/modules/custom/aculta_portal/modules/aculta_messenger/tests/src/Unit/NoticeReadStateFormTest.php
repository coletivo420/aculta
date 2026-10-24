<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Form\NoticeReadStateForm;
use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\aculta_messenger\Service\NoticeReadStateManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the CSRF-protected central read-state form.
 */
#[CoversClass(NoticeReadStateForm::class)]
#[Group('aculta_messenger')]
final class NoticeReadStateFormTest extends UnitTestCase {

  /**
   * A supported notice toggles state and returns to the filtered account list.
   */
  public function testSubmitChangesStateAndPreservesFilters(): void {
    $readState = $this->createMock(NoticeReadStateManagerInterface::class);
    $account = $this->createMock(AccountInterface::class);
    $readState->expects(self::once())->method('setRead')->with(28, $account, FALSE)->willReturn(TRUE);
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/avisos-mensagens/avisos?origem=wiki&leitura=unread'));
    $form = $this->form($readState, $account, $requestStack);
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())->method('addStatus');
    $form->setMessenger($messenger);
    $state = new FormState();
    $state->setValues(['message_id' => '28', 'target_read' => '0']);
    $build = [];

    $form->submitForm($build, $state);

    $redirect = $state->getRedirect();
    self::assertSame('aculta_messenger.avisos', $redirect->getRouteName());
    self::assertSame(['aba' => 'avisos'], $redirect->getRouteParameters());
    self::assertSame(['origem' => 'wiki', 'leitura' => 'unread'], $redirect->getOptions()['query']);
  }

  /**
   * Tampered IDs and a revoked source are rejected without a success redirect.
   */
  public function testRejectedStateChangeHasNoRedirect(): void {
    $readState = $this->createMock(NoticeReadStateManagerInterface::class);
    $account = $this->createMock(AccountInterface::class);
    $readState->expects(self::never())->method('setRead');
    $form = $this->form($readState, $account, new RequestStack());
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::never())->method('addStatus');
    $form->setMessenger($messenger);
    $state = new FormState();
    $state->setValues(['message_id' => '0', 'target_read' => '1']);
    $build = [];

    $form->submitForm($build, $state);

    self::assertArrayHasKey('message_id', $state->getErrors());
    self::assertNull($state->getRedirect());
  }

  /**
   * Creates the form with isolated injected dependencies.
   */
  private function form(NoticeReadStateManagerInterface $readState, AccountInterface $account, RequestStack $requestStack): NoticeReadStateForm {
    $form = new NoticeReadStateForm($readState, $account, new NoticeListFilter(), $requestStack);
    $form->setStringTranslation($this->getStringTranslationStub());
    return $form;
  }

}

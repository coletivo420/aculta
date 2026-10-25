<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_messenger\Form\AvisosPrivacyForm;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers followed-topic controls embedded in account privacy settings.
 */
#[CoversClass(AvisosPrivacyForm::class)]
#[Group('aculta_messenger')]
final class AvisosPrivacyFormTest extends UnitTestCase {

  /**
   * Privacy settings manage topic follows, not per-origin email preferences.
   */
  public function testPrivacyFormKeepsRequiredNoticeAndTopicFollowControls(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(27);
    $form = [];

    $this->formBuilder($account)->build($form);

    self::assertArrayHasKey('aviso_sempre', $form['avisos']);
    self::assertArrayHasKey('assinaturas', $form['avisos']);
    self::assertArrayNotHasKey('email_wiki', $form['avisos']);
    self::assertSame('aculta_messenger.communication_settings', $form['avisos']['email_settings']['#url']->getRouteName());
  }

  /**
   * Shows owned followed topics and hides inaccessible topic titles.
   */
  public function testBuildListsOwnedFollowingsWithoutLeakingInaccessibleTopicTitles(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(27);
    $flag = $this->createMock(FlagInterface::class);
    $followedTopic = $this->topic(501, 'Tópico público', TRUE);
    $privateTopic = $this->topic(502, 'Conteúdo restrito', FALSE);
    $ownFollowing = $this->flagging(701, 27, 'seguir_topico', $followedTopic);
    $ownRestrictedFollowing = $this->flagging(702, 27, 'seguir_topico', $privateTopic);
    $otherUserFollowing = $this->flagging(703, 84, 'seguir_topico', $followedTopic);
    $otherFlag = $this->flagging(704, 27, 'salvar_topico', $followedTopic);
    $flagService = $this->createMock(FlagServiceInterface::class);
    $flagService->method('getFlagById')->with('seguir_topico')->willReturn($flag);
    $flagService->method('getAllFlaggingByUser')->with($account)->willReturn([
      $ownFollowing,
      $ownRestrictedFollowing,
      $otherUserFollowing,
      $otherFlag,
    ]);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->expects(self::once())
      ->method('loadMultiple')
      ->with(['501', '502'])
      ->willReturn([501 => $followedTopic, 502 => $privateTopic]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($nodeStorage);
    $formBuilder = $this->formBuilder($account, $flagService, NULL, $entityTypeManager);
    $form = [];

    $formBuilder->build($form);

    $rows = $form['avisos']['assinaturas']['topics']['#rows'];
    self::assertArrayHasKey('701', $rows);
    self::assertArrayHasKey('702', $rows);
    self::assertArrayNotHasKey('703', $rows);
    self::assertArrayNotHasKey('704', $rows);
    $restrictedTitle = $rows['702']['remove']['#title']->getArguments()['@topic'];
    self::assertSame('Tópico indisponível', $restrictedTitle->getUntranslatedString());
    self::assertSame('Tópico público', $rows['701']['topic']['#title']);
    self::assertSame('entity.node.canonical', $rows['701']['topic']['#url']->getRouteName());
    self::assertSame(
      ['aculta_messenger.avisos_privacy_form:removeSubscriptionsSubmit'],
      $form['avisos']['assinaturas']['remove_selected']['#submit'],
      'Removing follows does not invoke the unrelated profile form submit handlers.',
    );
  }

  /**
   * Submission revalidates ownership, flag and target before removing a follow.
   */
  public function testSubmitRevalidatesSelectedFlaggingOwnership(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(27);
    $topic = $this->topic(501, 'Tópico público', TRUE);
    $wrongBundleTopic = $this->topic(502, 'Outro conteúdo', TRUE, 'article');
    $ownFollowing = $this->flagging(701, 27, 'seguir_topico', $topic);
    $otherUserFollowing = $this->flagging(702, 84, 'seguir_topico', $topic);
    $otherFlag = $this->flagging(703, 27, 'salvar_topico', $topic);
    $nonNodeTarget = $this->flagging(704, 27, 'seguir_topico', $topic, 'comment');
    $wrongBundleFollowing = $this->flagging(705, 27, 'seguir_topico', $wrongBundleTopic);
    $flag = $this->createMock(FlagInterface::class);
    $flagService = $this->createMock(FlagServiceInterface::class);
    $flagService->expects(self::once())->method('getFlagById')->with('seguir_topico')->willReturn($flag);
    $flagService->expects(self::once())->method('getAllFlaggingByUser')->with($account)->willReturn([
      $ownFollowing,
      $otherUserFollowing,
      $otherFlag,
      $nonNodeTarget,
      $wrongBundleFollowing,
    ]);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->expects(self::once())->method('loadMultiple')->with(['501', '502'])->willReturn([
      501 => $topic,
      502 => $wrongBundleTopic,
    ]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($nodeStorage);
    $flagService->expects(self::once())->method('unflag')->with($flag, $topic, $account);
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())->method('addStatus');
    $formBuilder = $this->formBuilder(
      $account,
      $flagService,
      $messenger,
      $entityTypeManager,
    );
    $formState = new FormState();
    $formState->setValue(['assinaturas', 'topics'], [
      '701' => ['remove' => 1],
      '702' => ['remove' => 1],
      '703' => ['remove' => 1],
      '704' => ['remove' => 1],
      '705' => ['remove' => 1],
    ]);
    $form = [];

    $formBuilder->removeSubscriptionsSubmit($form, $formState);
  }

  /**
   * Creates the form builder with controlled Flag and URL services.
   */
  private function formBuilder(
    AccountProxyInterface $account,
    ?FlagServiceInterface $flagService = NULL,
    ?MessengerInterface $messenger = NULL,
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
  ): AvisosPrivacyForm {
    return new AvisosPrivacyForm(
      $account,
      $entityTypeManager ?? $this->createMock(EntityTypeManagerInterface::class),
      $flagService ?? $this->createMock(FlagServiceInterface::class),
      $this->domainPurposeManager(),
      $messenger ?? $this->createMock(MessengerInterface::class),
    );
  }

  /**
   * Builds a topic fixture with explicit access.
   */
  private function topic(int $id, string $title, bool $viewable, string $bundle = 'aculta_forum_topic'): NodeInterface {
    $access = $this->createMock(AccessResultInterface::class);
    $access->method('isAllowed')->willReturn($viewable);
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('id')->willReturn((string) $id);
    $topic->method('bundle')->willReturn($bundle);
    $topic->method('getTitle')->willReturn($title);
    $topic->method('access')->willReturn($access);
    return $topic;
  }

  /**
   * Builds a personal Flag record for the given owner and entity.
   */
  private function flagging(int $id, int $uid, string $flagId, NodeInterface $topic, string $targetType = 'node'): FlaggingInterface {
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('id')->willReturn((string) $id);
    $flagging->method('getOwnerId')->willReturn($uid);
    $flagging->method('getFlagId')->willReturn($flagId);
    $flagging->method('getFlaggableType')->willReturn($targetType);
    $flagging->method('getFlaggableId')->willReturn($topic->id());
    $flagging->method('getFlaggable')->willReturn($topic);
    return $flagging;
  }

  /**
   * Builds the Domain-purpose URL helper used for Forum links.
   */
  private function domainPurposeManager(): DomainPurposeManager {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->willReturnCallback(
      static fn(string $id): ?DomainInterface => in_array(
        $id,
        ['forum_aculta_org', 'conta_aculta_org'],
        TRUE,
      ) ? $domain : NULL,
    );
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->with('domain')->willReturn($domainStorage);
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn(NULL);
    return new DomainPurposeManager(
      $entityTypes,
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

}

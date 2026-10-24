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
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Form\AvisosPrivacyForm;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers member email preferences for all shared notice producers.
 */
#[CoversClass(AvisosPrivacyForm::class)]
#[Group('aculta_messenger')]
final class AvisosPrivacyFormTest extends UnitTestCase {

  /**
   * User can select all delivery modes for every integrated origin.
   */
  public function testBuildOffersSameEmailModesForEveryOrigin(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturn(AvisoPreferencias::EMAIL_DIGEST);
    $preferences = new AvisoPreferencias($userData);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(27);
    $formBuilder = $this->formBuilder($account, $preferences);
    $form = [];

    $formBuilder->build($form);

    $expectedOptions = [
      AvisoPreferencias::EMAIL_OFF,
      AvisoPreferencias::EMAIL_DIGEST,
      AvisoPreferencias::EMAIL_IMMEDIATE,
    ];
    foreach ([
      'email_respostas',
      'email_atualizacoes',
      'email_aprendizagem',
      'email_wiki',
      'email_loja',
      'email_doacao',
    ] as $element) {
      self::assertSame($expectedOptions, array_keys($form['avisos'][$element]['#options']), $element);
    }
    self::assertArrayHasKey('resumo_email', $form['avisos'], 'Digest delivery remains a distinct general opt-in.');
    self::assertArrayHasKey('aviso_sempre', $form['avisos'], 'Site notices remain mandatory.');
  }

  /**
   * Admin policy removes delivery choices from the member form.
   */
  public function testBuildRespectsAdministrativeModePolicy(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $settings = $this->getConfigFactoryStub([
      'aculta_messenger.settings' => [
        'email_modes' => ['wiki' => ['digest' => TRUE, 'immediate' => FALSE]],
      ],
    ]);
    $preferences = new AvisoPreferencias($userData, $settings);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(27);
    $formBuilder = $this->formBuilder($account, $preferences);
    $form = [];

    $formBuilder->build($form);

    self::assertSame(
      [AvisoPreferencias::EMAIL_OFF, AvisoPreferencias::EMAIL_DIGEST],
      array_keys($form['avisos']['email_wiki']['#options']),
    );
    self::assertContains(AvisoPreferencias::EMAIL_OFF, array_keys($form['avisos']['email_doacao']['#options']));
  }

  /**
   * Saves per-origin modes while preserving mandatory site notices.
   */
  public function testSubmitSavesModesForAllIntegratedOrigins(): void {
    $saved = [];
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::exactly(9))
      ->method('set')
      ->willReturnCallback(static function (string $module, int $uid, string $key, mixed $value) use (&$saved): void {
        self::assertSame(AvisoPreferencias::MODULO, $module);
        self::assertSame(27, $uid);
        $saved[$key] = $value;
      });
    $preferences = new AvisoPreferencias($userData);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(27);
    $formBuilder = $this->formBuilder($account, $preferences);
    $formState = new FormState();
    $formState->setValue('resumo_email', FALSE);
    $formState->setValue('email_respostas', AvisoPreferencias::EMAIL_DIGEST);
    $formState->setValue('email_atualizacoes', AvisoPreferencias::EMAIL_OFF);
    $formState->setValue('email_aprendizagem', AvisoPreferencias::EMAIL_IMMEDIATE);
    $formState->setValue('email_wiki', AvisoPreferencias::EMAIL_OFF);
    $formState->setValue('email_loja', AvisoPreferencias::EMAIL_DIGEST);
    $formState->setValue('email_doacao', AvisoPreferencias::EMAIL_IMMEDIATE);
    $form = [];

    $formBuilder->submit($form, $formState);

    self::assertSame([
      'avisos_resumo_email' => 0,
      'avisos_resumo_forum_resposta' => 1,
      'avisos_resumo_forum_atualizacao' => 0,
      'avisos_email_mode_forum_resposta' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_email_mode_forum_atualizacao' => AvisoPreferencias::EMAIL_OFF,
      'avisos_email_mode_aprendizagem' => AvisoPreferencias::EMAIL_IMMEDIATE,
      'avisos_email_mode_wiki' => AvisoPreferencias::EMAIL_OFF,
      'avisos_email_mode_loja' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_email_mode_doacao' => AvisoPreferencias::EMAIL_IMMEDIATE,
    ], $saved);
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
    $formBuilder = $this->formBuilder($account, new AvisoPreferencias($this->createMock(UserDataInterface::class)), $flagService);
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
   * Submission revalidates ownership and flag type before removing a follow.
   */
  public function testSubmitRevalidatesSelectedFlaggingOwnership(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(27);
    $topic = $this->topic(501, 'Tópico público', TRUE);
    $ownFollowing = $this->flagging(701, 27, 'seguir_topico', $topic);
    $otherUserFollowing = $this->flagging(702, 84, 'seguir_topico', $topic);
    $flag = $this->createMock(FlagInterface::class);
    $flagService = $this->createMock(FlagServiceInterface::class);
    $flagService->expects(self::once())->method('getFlagById')->with('seguir_topico')->willReturn($flag);
    $flagService->expects(self::once())->method('getAllFlaggingByUser')->with($account)->willReturn([
      $ownFollowing,
      $otherUserFollowing,
    ]);
    $flagService->expects(self::once())->method('unflag')->with($flag, $topic, $account);
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects(self::once())->method('addStatus');
    $formBuilder = $this->formBuilder(
      $account,
      new AvisoPreferencias($this->createMock(UserDataInterface::class)),
      $flagService,
      $messenger,
    );
    $formState = new FormState();
    $formState->setValue(['assinaturas', 'topics'], [
      '701' => ['remove' => 1],
      '702' => ['remove' => 1],
    ]);
    $form = [];

    $formBuilder->removeSubscriptionsSubmit($form, $formState);
  }

  /**
   * Creates the form builder with controlled Flag and URL services.
   */
  private function formBuilder(
    AccountProxyInterface $account,
    AvisoPreferencias $preferences,
    ?FlagServiceInterface $flagService = NULL,
    ?MessengerInterface $messenger = NULL,
  ): AvisosPrivacyForm {
    return new AvisosPrivacyForm(
      $account,
      $preferences,
      $flagService ?? $this->createMock(FlagServiceInterface::class),
      $this->domainPurposeManager(),
      $messenger ?? $this->createMock(MessengerInterface::class),
    );
  }

  /**
   * Builds a topic fixture with explicit access.
   */
  private function topic(int $id, string $title, bool $viewable): NodeInterface {
    $access = $this->createMock(AccessResultInterface::class);
    $access->method('isAllowed')->willReturn($viewable);
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('id')->willReturn((string) $id);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getTitle')->willReturn($title);
    $topic->method('access')->willReturn($access);
    return $topic;
  }

  /**
   * Builds a personal Flag record for the given owner and entity.
   */
  private function flagging(int $id, int $uid, string $flagId, NodeInterface $topic): FlaggingInterface {
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('id')->willReturn((string) $id);
    $flagging->method('getOwnerId')->willReturn($uid);
    $flagging->method('getFlagId')->willReturn($flagId);
    $flagging->method('getFlaggableType')->willReturn('node');
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
    $domainStorage->method('load')->with('forum_aculta_org')->willReturn($domain);
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

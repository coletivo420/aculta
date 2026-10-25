<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\aculta_portal\Form\AccountOtherDataForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * CPF/CNPJ remains an owner-controlled, private, single-write form.
 *
 * These Unit tests do not replace a Kernel test with the real Profile storage.
 */
#[CoversClass(AccountOtherDataForm::class)]
#[Group('aculta_portal')]
final class AccountOtherDataFormContractTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Without aculta_id_cpf, never offer a document input or submit button.
   */
  public function testMissingOwnerIsSafelyUnavailable(): void {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getStorage');
    $form = $this->form(NULL, $entities)->buildForm([], $this->state());
    self::assertArrayHasKey('unavailable', $form);
    self::assertArrayNotHasKey('document', $form);
    self::assertArrayNotHasKey('actions', $form);
    self::assertSame(0, $form['#cache']['max-age']);
    self::assertContains('user', $form['#cache']['contexts']);
  }

  /**
   * With missing encryption, no user record or submitted value is touched.
   */
  public function testUnavailableCryptoHidesDocumentInput(): void {
    $documents = $this->createMock(AccountDocumentInterface::class);
    $documents->method('isAvailable')->willReturn(FALSE);
    $documents->expects(self::never())->method('savedDocument');
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getStorage');
    $form = $this->form($documents, $entities)->buildForm([], $this->state());
    self::assertArrayHasKey('unavailable', $form);
    self::assertArrayNotHasKey('document', $form);
  }

  /**
   * An existing document is shown only in the masked, disabled field.
   */
  public function testSavedDocumentIsReadOnlyAndMasked(): void {
    $masked = '974.12*.***-**';
    $documents = $this->createMock(AccountDocumentInterface::class);
    $documents->method('isAvailable')->willReturn(TRUE);
    $documents->method('savedDocument')->willReturn($masked);
    $documents->expects(self::never())->method('canSave');
    $form = $this->form($documents)->buildForm([], $this->state());
    self::assertSame($masked, $form['saved']['#default_value']);
    self::assertTrue($form['saved']['#disabled']);
    self::assertArrayNotHasKey('document', $form);
    self::assertArrayNotHasKey('actions', $form);
  }

  /**
   * Missing fingerprint capability never leaves a writable document editor.
   */
  public function testOwnerCanReadButCannotSave(): void {
    $documents = $this->createMock(AccountDocumentInterface::class);
    $documents->method('isAvailable')->willReturn(TRUE);
    $documents->method('savedDocument')->willReturn(NULL);
    $documents->method('canSave')->willReturn(FALSE);
    $form = $this->form($documents)->buildForm([], $this->state());
    self::assertArrayHasKey('unavailable', $form);
    self::assertArrayNotHasKey('document', $form);
    self::assertArrayNotHasKey('actions', $form);
  }

  /**
   * Errors reading encrypted values must not reveal any private input.
   */
  public function testUnreadableEncryptedDocumentFailsClosed(): void {
    $documents = $this->createMock(AccountDocumentInterface::class);
    $documents->method('isAvailable')->willReturn(TRUE);
    $documents->method('savedDocument')->willThrowException(new \RuntimeException('Sensitive cipher internals'));
    $form = $this->form($documents)->buildForm([], $this->state());
    self::assertArrayHasKey('unavailable', $form);
    self::assertStringNotContainsString('Sensitive', (string) $form['unavailable']['#plain_text']);
    self::assertArrayNotHasKey('document', $form);
  }

  /**
   * New entries are accepted only via native Form API when the owner is ready.
   */
  public function testAvailableOwnerShowsNormalFormApiInput(): void {
    $documents = $this->createMock(AccountDocumentInterface::class);
    $documents->method('isAvailable')->willReturn(TRUE);
    $documents->method('savedDocument')->willReturn(NULL);
    $documents->method('canSave')->willReturn(TRUE);
    $form = $this->form($documents)->buildForm([], $this->state());
    self::assertTrue($form['document']['#required']);
    self::assertSame('textfield', $form['document']['#type']);
    self::assertSame('submit', $form['actions']['submit']['#type']);
    self::assertArrayNotHasKey('saved', $form);
  }

  /**
   * Invalid values receive a generic error without reproducing the input.
   */
  public function testInvalidDocumentShowsGenericError(): void {
    $documents = $this->createMock(AccountDocumentInterface::class);
    $documents->method('canonicalize')->willReturn(NULL);
    $state = $this->state();
    $state->method('getValue')->with('document')->willReturn('raw-confidential-input');
    $state->expects(self::once())->method('setErrorByName')->with('document', self::callback(
      static fn ($message): bool => !str_contains((string) $message, 'raw-confidential-input')
    ));
    $form = [];
    $this->form($documents)->validateForm($form, $state);
  }

  /**
   * A valid document is retained in FormState only, never in form markup.
   */
  public function testCanonicalDocumentStoredInFormState(): void {
    $documents = $this->createMock(AccountDocumentInterface::class);
    $documents->method('canonicalize')->willReturn('12345678909');
    $state = $this->state();
    $state->method('getValue')->with('document')->willReturn('123.456.789-09');
    $state->expects(self::once())->method('set')->with('canonical', '12345678909');
    $state->expects(self::never())->method('setErrorByName');
    $form = [];
    $this->form($documents)->validateForm($form, $state);
  }

  private function state(): FormStateInterface {
    return $this->createMock(FormStateInterface::class);
  }

  /**
   * Builds with the real form dependencies, no duplicate storage or service.
   */
  private function form(?AccountDocumentInterface $documents, ?EntityTypeManagerInterface $entities = NULL): AccountOtherDataForm {
    if ($entities === NULL) {
      $storage = $this->createMock(EntityStorageInterface::class);
      $storage->method('load')->with('7')->willReturn($this->createMock(UserInterface::class));
      $entities = $this->createMock(EntityTypeManagerInterface::class);
      $entities->method('getStorage')->with('user')->willReturn($storage);
    }
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('7');
    return new AccountOtherDataForm(
      $documents,
      $account,
      $entities,
      $this->createMock(MessengerInterface::class),
      new RequestStack(),
    );
  }

}

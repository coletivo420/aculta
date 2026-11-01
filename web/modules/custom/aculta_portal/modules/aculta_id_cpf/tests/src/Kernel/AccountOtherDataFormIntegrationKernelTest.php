<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_id_cpf\Kernel;

use Drupal\aculta_id_cpf\Service\AccountDocumentStore;
use Drupal\aculta_id_cpf\Service\CnpjValidator;
use Drupal\aculta_id_cpf\Service\CpfValidator;
use Drupal\aculta_id_cpf\Service\DocumentCipher;
use Drupal\aculta_id_cpf\Service\DocumentFingerprint;
use Drupal\aculta_id_cpf\Service\DocumentFormatter;
use Drupal\aculta_id_cpf\Service\DocumentNormalizer;
use Drupal\aculta_id_cpf\Service\DocumentValidator;
use Drupal\aculta_portal\Form\AccountOtherDataForm;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\profile\Entity\ProfileType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Covers the Portal form with the real encrypted account document store.
 */
#[Group('aculta_id_cpf')]
#[RunTestsInSeparateProcesses]
final class AccountOtherDataFormIntegrationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'profile',
    'key',
    'aculta_id_cpf',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installConfig(['system', 'user', 'field']);

    ProfileType::create([
      'id' => 'associado',
      'label' => 'Associado',
      'display_label' => 'Perfil de associado',
      'multiple' => TRUE,
    ])->save();
    foreach (['field_documento', 'field_documento_indice'] as $field_name) {
      $storage = FieldStorageConfig::create([
        'entity_type' => 'profile',
        'field_name' => $field_name,
        'type' => 'string',
      ]);
      $storage->save();
      FieldConfig::create([
        'entity_type' => 'profile',
        'bundle' => 'associado',
        'field_name' => $field_name,
        'label' => $field_name,
      ])->save();
    }
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Form API validation and submit save only ciphertext and a fingerprint.
   */
  public function testFormUsesRealStoreAndDisplaysOnlyTheMask(): void {
    $user = User::create(['name' => 'cpf-form-kernel-owner', 'status' => 1]);
    $user->save();
    $messages = $this->createMock(MessengerInterface::class);
    $messages->expects(self::once())->method('addStatus');
    $store = $this->store();
    $form = $this->form($store, $user, $messages);
    $state = new FormState();
    $state->setValue('document', '529.982.247-25');

    $build = $form->buildForm([], $state);
    self::assertSame('textfield', $build['document']['#type']);
    self::assertTrue($build['document']['#required']);
    $form->validateForm($build, $state);
    self::assertSame('52998224725', $state->get('canonical'));
    self::assertSame([], $state->getErrors());
    $form->submitForm($build, $state);

    $profile = $this->container->get('entity_type.manager')->getStorage('profile')
      ->loadByUser($user, 'associado');
    self::assertNotSame('52998224725', $profile->get('field_documento')->value);
    self::assertSame(64, strlen($profile->get('field_documento_indice')->value));

    $saved_build = $form->buildForm([], new FormState());
    self::assertSame('529.98*.***-**', $saved_build['saved']['#default_value']);
    self::assertTrue($saved_build['saved']['#disabled']);
    self::assertArrayNotHasKey('document', $saved_build);
  }

  /**
   * Duplicate documents use a generic form message and create no second row.
   */
  public function testFormDoesNotRevealDuplicateDocument(): void {
    $store = $this->store();
    $owner = User::create(['name' => 'cpf-form-kernel-first', 'status' => 1]);
    $owner->save();
    $store->save($owner, '52998224725');

    $other = User::create(['name' => 'cpf-form-kernel-second', 'status' => 1]);
    $other->save();
    $messages = $this->createMock(MessengerInterface::class);
    $messages->expects(self::once())
      ->method('addError')
      ->with(self::callback(static fn($message): bool => !str_contains((string) $message, '52998224725')));
    $form = $this->form($store, $other, $messages);
    $state = new FormState();
    $state->setValue('document', '52998224725');
    $build = $form->buildForm([], $state);
    $form->validateForm($build, $state);
    $form->submitForm($build, $state);

    self::assertNull($store->savedDocument($other));
  }

  /**
   * Builds the concrete store with deterministic test-only keys.
   */
  private function store(): AccountDocumentStore {
    $validator = new DocumentValidator(
      new DocumentNormalizer(),
      new CpfValidator(),
      new CnpjValidator(),
    );
    $values = [
      'aculta_document_encryption_key' => str_repeat('a', 64),
      DocumentFingerprint::KEY_ID => str_repeat('test-fingerprint-key-', 2),
    ];
    $keys = [];
    foreach ($values as $id => $value) {
      $keys[$id] = $this->createMock(KeyInterface::class);
      $keys[$id]->method('getKeyValue')->willReturn($value);
    }
    $repository = $this->createMock(KeyRepositoryInterface::class);
    $repository->method('getKey')->willReturnCallback(
      static fn(string $id): ?KeyInterface => $keys[$id] ?? NULL,
    );

    return new AccountDocumentStore(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $validator,
      new DocumentFormatter($validator),
      new DocumentCipher($repository),
      new DocumentFingerprint($validator, $repository),
    );
  }

  /**
   * Creates the Portal form around the real store and a real account.
   */
  private function form(AccountDocumentStore $store, User $user, MessengerInterface $messages): AccountOtherDataForm {
    $request = Request::create('/minha-conta/outros-dados?destination=%2Fcheckout%2F5');
    $requests = new RequestStack();
    $requests->push($request);
    return new AccountOtherDataForm(
      $store,
      $user,
      $this->container->get('entity_type.manager'),
      $messages,
      $requests,
    );
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_id_cpf\Kernel;

use Drupal\aculta_id_cpf\Enum\DocumentType;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies CPF account resolution and login form integration.
 */
#[Group('aculta_id_cpf')]
#[RunTestsInSeparateProcesses]
final class CpfLoginIdentifierResolverKernelTest extends KernelTestBase {

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
      FieldStorageConfig::create([
        'entity_type' => 'profile',
        'field_name' => $field_name,
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'entity_type' => 'profile',
        'bundle' => 'associado',
        'field_name' => $field_name,
        'label' => $field_name,
      ])->save();
    }
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->installFingerprintKey();
  }

  /**
   * A saved CPF resolves only to the owning account name.
   */
  public function testValidCpfResolvesToItsAccount(): void {
    $user = $this->createAccountWithCpf('cpf-login-kernel-owner', '52998224725');

    self::assertSame(
      $user->getAccountName(),
      $this->container->get('aculta_id_cpf.login_identifier_resolver')->resolveUsername('529.982.247-25'),
    );
  }

  /**
   * Invalid, unknown, CNPJ, and ambiguous identifiers fail closed.
   */
  public function testUnresolvedAndAmbiguousIdentifiersReturnNull(): void {
    $this->createAccountWithCpf('cpf-login-kernel-duplicate-a', '52998224725');
    $this->createAccountWithCpf('cpf-login-kernel-duplicate-b', '52998224725');
    $resolver = $this->container->get('aculta_id_cpf.login_identifier_resolver');

    self::assertNull($resolver->resolveUsername('11111111111'));
    self::assertNull($resolver->resolveUsername('52998224724'));
    self::assertNull($resolver->resolveUsername('11222333000181'));
  }

  /**
   * Form callbacks expose the submitted CPF again before final validation.
   */
  public function testFormCallbacksRestoreTheSubmittedIdentifier(): void {
    $user = $this->createAccountWithCpf('cpf-login-kernel-restore', '52998224725');
    $state = new FormState();
    $state->setUserInput(['name' => '529.982.247-25', 'pass' => 'password']);
    $state->setValue('name', '529.982.247-25');
    $state->setValue('pass', 'password');
    $callbacks = $this->container->get('aculta_id_cpf.login_form_callbacks');
    $form = [];

    $callbacks->resolveIdentifier($form, $state);
    self::assertSame($user->getAccountName(), $state->getValue('name'));
    $callbacks->restoreIdentifier($form, $state);
    self::assertSame('529.982.247-25', $state->getValue('name'));
  }

  /**
   * Both local login surfaces wrap native authentication with resolution.
   */
  public function testBothLoginFormsWrapTheirAuthenticationValidator(): void {
    $main = [
      '#validate' => [
        'aculta_portal.form_callbacks:validateLoginAuthentication',
        '::validateFinal',
      ],
    ];
    $popup = [
      '#validate' => [
        'aculta_portal.form_callbacks:validateLoginAuthentication',
        '::validateFinal',
      ],
    ];
    $other = ['#validate' => ['::validateAuthentication']];
    $state = new FormState();

    aculta_id_cpf_form_alter($main, $state, 'user_login_form');
    aculta_id_cpf_form_alter($popup, $state, 'aculta_login_popup_form');
    aculta_id_cpf_form_alter($other, $state, 'user_pass_reset');

    self::assertSame([
      'aculta_id_cpf.login_form_callbacks:resolveIdentifier',
      'aculta_portal.form_callbacks:validateLoginAuthentication',
      'aculta_id_cpf.login_form_callbacks:restoreIdentifier',
      '::validateFinal',
    ], $main['#validate']);
    self::assertSame([
      'aculta_id_cpf.login_form_callbacks:resolveIdentifier',
      'aculta_portal.form_callbacks:validateLoginAuthentication',
      'aculta_id_cpf.login_form_callbacks:restoreIdentifier',
      '::validateFinal',
    ], $popup['#validate']);
    self::assertSame(['::validateAuthentication'], $other['#validate']);
  }

  /**
   * Installs a deterministic key for isolated CPF fingerprint assertions.
   */
  private function installFingerprintKey(): void {
    $key = $this->createMock(KeyInterface::class);
    $key->method('getKeyValue')->willReturn(str_repeat('test-fingerprint-key-', 2));
    $repository = $this->createMock(KeyRepositoryInterface::class);
    $repository->method('getKey')->willReturnCallback(
      static fn(string $id): ?KeyInterface => $id === 'aculta_id_cpf_fingerprint' ? $key : NULL,
    );
    $this->container->set('key.repository', $repository);
  }

  /**
   * Creates an account with an indexed CPF but no plaintext document value.
   */
  private function createAccountWithCpf(string $name, string $cpf): User {
    $user = User::create(['name' => $name, 'status' => 1]);
    $user->save();
    $profile = Profile::create([
      'type' => 'associado',
      'uid' => $user->id(),
      'status' => TRUE,
    ]);
    $profile->set('field_documento_indice', $this->container
      ->get('aculta_id_cpf.fingerprint')
      ->fingerprint($cpf, DocumentType::CPF));
    $profile->save();
    return $user;
  }

}

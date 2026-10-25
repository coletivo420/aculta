<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_messenger\Form\PrivateMessageFormValidator;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\aculta_messenger\Service\ConversationWriteLock;
use Drupal\KernelTests\KernelTestBase;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormState;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Lock\PersistentDatabaseLockBackend;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises private-message Flood validation with Drupal's database services.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class PrivateMessageFloodKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user']);
  }

  /**
   * The database Flood limit applies only after a persisted send is registered.
   */
  public function testDatabaseFloodLimitCountsPersistedSubmissions(): void {
    $account = User::create([
      'name' => 'flood_member',
      'mail' => 'flood-member@example.invalid',
      'status' => 1,
    ]);
    $account->save();
    $this->container->get('current_user')->setAccount($account);
    $flood = $this->container->get('flood');
    $form = [];

    for ($attempt = 0; $attempt < 10; $attempt++) {
      $validator = $this->validator($flood, $this->container->get('lock.persistent'));
      $state = new FormState();
      $validator->validateRateLimit($form, $state);
      self::assertFalse($state->hasAnyErrors());
      $validator->registerRateLimit($form, $state);
    }

    $eleventh = $this->validator($flood, $this->container->get('lock.persistent'));
    $state = new FormState();
    $eleventh->validateRateLimit($form, $state);

    self::assertSame(
      'Você atingiu o limite temporário de envio de mensagens. Tente novamente mais tarde.',
      (string) $state->getErrors()['message'],
    );
  }

  /**
   * An independent backend cannot pass Flood while a sender owns the lock.
   */
  public function testIndependentFloodCheckIsRejectedWhileSenderLockIsHeld(): void {
    $account = User::create([
      'name' => 'flood_concurrent_member',
      'mail' => 'flood-concurrent@example.invalid',
      'status' => 1,
    ]);
    $account->save();
    $this->container->get('current_user')->setAccount($account);
    $flood = $this->container->get('flood');
    $form = [];
    $first = $this->validator($flood, $this->container->get('lock.persistent'));
    $firstState = new FormState();
    $first->validateRateLimit($form, $firstState);
    self::assertFalse($firstState->hasAnyErrors());

    $second = $this->validator($flood, new PersistentDatabaseLockBackend($this->container->get('database')));
    $secondState = new FormState();
    $second->validateRateLimit($form, $secondState);
    self::assertArrayHasKey('message', $secondState->getErrors());
    $secondState->clearErrors();

    $first->registerRateLimit($form, $firstState);
    self::assertSame(0, (int) $this->container->get('database')->select('semaphore', 'lock')
      ->condition('name', 'aculta_messenger.private_message_flood.' . $account->id())
      ->countQuery()
      ->execute()
      ->fetchField(), 'The persisted submission releases its sender lock.');
    $afterRelease = $this->validator($flood, new PersistentDatabaseLockBackend($this->container->get('database')));
    $afterReleaseState = new FormState();
    $afterRelease->validateRateLimit($form, $afterReleaseState);
    self::assertFalse($afterReleaseState->hasAnyErrors());
    $afterRelease->registerRateLimit($form, $afterReleaseState);
  }

  /**
   * Creates a validator with real Flood/UserData and a selected lock backend.
   */
  private function validator(FloodInterface $flood, LockBackendInterface $lock): PrivateMessageFormValidator {
    return new PrivateMessageFormValidator(
      $flood,
      $this->container->get('current_user'),
      $this->container->get('string_translation'),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $this->createMock(FriendshipManagerInterface::class),
      $this->container->get('user.data'),
      $lock,
      new ConversationPolicy($this->container->get('config.factory')),
      new ConversationWriteLock($lock, $this->container->get('entity_type.manager')),
      $this->container->get('entity_type.manager'),
    );
  }

}

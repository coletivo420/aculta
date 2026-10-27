<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_user\Account\NicknamePolicy;
use Drupal\aculta_user\Avatar\AvatarPool;
use Drupal\aculta_user\Hook\AccountCreationHooks;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\ProfileStorageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests generated display nicknames for new accounts.
 */
#[CoversClass(AccountCreationHooks::class)]
#[Group('aculta_user')]
final class AccountCreationHooksTest extends UnitTestCase {

  /**
   * OAuth username collision retains the display suffix and canonical slug.
   */
  public function testOauthUsernameCollisionCreatesHashDisplayNickname(): void {
    $profile = $this->createMock(ProfileInterface::class);
    $profile->expects(self::once())
      ->method('save');
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(0);
    $storage = $this->createMock(ProfileStorageInterface::class);
    $storage->expects(self::exactly(count(PerfilDoPapel::TIPOS)))
      ->method('loadByUser')
      ->willReturn(NULL);
    $storage->expects(self::once())
      ->method('create')
      ->with(self::callback(static function (array $values): bool {
        return $values['uid'] === 27
          && $values['field_nickname'] === 'nome#2';
      }))
      ->willReturn($profile);
    $storage->method('getQuery')->willReturn($query);

    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')
      ->with('profile')
      ->willReturn($storage);
    $avatarPool = new AvatarPool(
      $this->createMock(ExtensionList::class),
      $this->createMock(FileSystemInterface::class),
      $entities,
      $this->createMock(UserDataInterface::class),
      '/unused',
    );
    $transliteration = $this->createMock(PhpTransliteration::class);
    $transliteration->method('transliterate')
      ->willReturnArgument(0);
    $nicknamePolicy = new NicknamePolicy($entities);

    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn(27);
    $account->method('getAccountName')->willReturn('nome-2');
    $account->method('getEmail')->willReturn('nome@example.test');
    $account->method('getRoles')->willReturn(['authenticated']);

    (new AccountCreationHooks($entities, $avatarPool, $transliteration, $nicknamePolicy))
      ->userInsert($account);
  }

}

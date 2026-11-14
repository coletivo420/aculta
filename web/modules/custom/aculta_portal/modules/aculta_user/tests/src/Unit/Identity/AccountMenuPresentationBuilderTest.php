<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Identity;

use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\aculta_user\Identity\AccountMenuPresentationBuilder;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\ProfileStorageInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies the account menu gets the user's nickname as display name.
 */
#[Group('aculta_user')]
final class AccountMenuPresentationBuilderTest extends UnitTestCase {

  /**
   * Sets up the translation service used by the avatar's accessible label.
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * The trimmed nickname is included in the Portal presentation.
   */
  public function testBuildReturnsTrimmedNickname(): void {
    $presentation = $this->builder('  Conta Exemplo  ')->build($this->account());

    self::assertSame('Conta Exemplo', $presentation['account_name']);
    self::assertNull($presentation['avatar']);
    self::assertContains('user', $presentation['cacheability']->getCacheContexts());
  }

  /**
   * An empty nickname does not fall back to username or an initial.
   */
  public function testBuildReturnsNullForEmptyNickname(): void {
    $presentation = $this->builder('   ')->build($this->account());

    self::assertNull($presentation['account_name']);
  }

  /**
   * Creates the builder with a profile containing the supplied nickname.
   */
  private function builder(string $nickname): AccountMenuPresentationBuilder {
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('getCacheContexts')->willReturn([]);
    $profile->method('getCacheTags')->willReturn(['profile:1']);
    $profile->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $profile->method('hasField')->willReturnCallback(
      static fn (string $field): bool => $field === 'field_nickname',
    );
    $nicknameField = $this->createMock(FieldItemListInterface::class);
    $nicknameField->method('__get')->with('value')->willReturn($nickname);
    $profile->method('get')->with('field_nickname')->willReturn($nicknameField);

    $profileStorage = $this->createMock(ProfileStorageInterface::class);
    $profileStorage->method('loadByUser')->willReturnCallback(
      static fn (UserInterface $user, string $type): ?ProfileInterface => $type === 'authenticated'
        ? $profile
        : NULL,
    );
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('profile')->willReturn($profileStorage);
    $avatarResolver = new AvatarResolver(
      new ImageRenderer(new ImageCatalog(), $entityTypeManager),
    );

    return new AccountMenuPresentationBuilder(
      $entityTypeManager,
      new PublicProfilePolicy(),
      $avatarResolver,
    );
  }

  /**
   * Creates an account with no personal photo.
   */
  private function account(): UserInterface {
    $account = $this->createMock(UserInterface::class);
    $account->method('getRoles')->willReturn(['authenticated']);
    $account->method('hasField')->with('user_picture')->willReturn(FALSE);
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheTags')->willReturn(['user:1']);
    $account->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    return $account;
  }

}

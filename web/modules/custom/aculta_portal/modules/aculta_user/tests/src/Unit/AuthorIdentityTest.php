<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\ProfileStorageInterface;
use Drupal\aculta_user\Identity\AuthorIdentity;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\ImageRenderer;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifica o contrato escalar e cacheável da identidade de autoria.
 */
#[Group('aculta_user')]
final class AuthorIdentityTest extends UnitTestCase {

  public function testViewModelExpõeSomenteIdentidadeNeutra(): void {
    $cacheability = (new CacheableMetadata())
      ->setCacheTags(['user:42', 'profile:7'])
      ->setCacheContexts(['user.permissions']);
    $identity = new AuthorIdentity('author_forum', 'Maria', '/u/maria', NULL, $cacheability);

    $this->assertSame([
      'variant' => 'author_forum',
      'display_name' => 'Maria',
      'profile_url' => '/u/maria',
      'avatar' => NULL,
    ], $identity->toArray());
    $this->assertSame(['user:42', 'profile:7'], $identity->getCacheTags());
    $this->assertSame(['user.permissions'], $identity->getCacheContexts());
    $this->assertSame(-1, $identity->getCacheMaxAge());
  }

  public function testResolverProduzSomenteApelidoEPerfilPrivadoSemLink(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('42');
    $account->method('getRoles')->willReturn(['authenticated']);
    $account->method('getCacheTags')->willReturn(['user:42']);
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheMaxAge')->willReturn(-1);

    $nickname = $this->createMock(FieldItemListInterface::class);
    $nickname->method('__get')->with('value')->willReturn('Maria');
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('hasField')->willReturnCallback(static fn (string $field): bool => $field === 'field_nickname');
    $profile->method('get')->willReturnCallback(fn (string $field): FieldItemListInterface => $nickname);
    $profile->method('getCacheTags')->willReturn(['profile:7']);
    $profile->method('getCacheContexts')->willReturn([]);
    $profile->method('getCacheMaxAge')->willReturn(-1);

    $storage = $this->createMock(ProfileStorageInterface::class);
    $storage->method('loadByUser')->willReturnCallback(static fn (UserInterface $user, string $type): ?ProfileInterface => $type === 'authenticated' ? $profile : NULL);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('profile')->willReturn($storage);
    $profileLink = $this->createMock(PublicProfileLinkInterface::class);
    $profileLink->method('build')->willReturn(NULL);

    $imageRenderer = new ImageRenderer(new ImageCatalog(), $this->createMock(EntityTypeManagerInterface::class));
    $resolver = new AuthorIdentityResolver(
      $entityTypeManager,
      $profileLink,
      $imageRenderer,
      new \Drupal\aculta_user\PublicProfilePolicy(),
    );
    $identity = $resolver->resolve($account, 'author_forum');

    $this->assertInstanceOf(AuthorIdentity::class, $identity);
    $this->assertSame([
      'variant' => 'author_forum',
      'display_name' => 'Maria',
      'profile_url' => NULL,
      'avatar' => NULL,
    ], $identity->toArray());
    $this->assertSame(['user:42', 'profile:7'], $identity->getCacheTags());
  }

  public function testLegacyAuthorNaoPodeSerConvertidoEmUser(): void {
    $resolver = new AuthorIdentityResolver(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(PublicProfileLinkInterface::class),
      new ImageRenderer(new ImageCatalog(), $this->createMock(EntityTypeManagerInterface::class)),
      new \Drupal\aculta_user\PublicProfilePolicy(),
    );

    $this->expectException(\InvalidArgumentException::class);
    $resolver->resolve($this->createMock(UserInterface::class), 'author_growroom_historico');
  }

}

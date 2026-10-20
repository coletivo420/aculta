<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\ProfileStorageInterface;
use Drupal\aculta_user\Identity\AuthorIdentity;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\aculta_user\PublicProfilePolicy;
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
    $identity = new AuthorIdentity('author_forum', 'Maria', '/u/maria', NULL, '2021', $cacheability);

    $this->assertSame([
      'variant' => 'author_forum',
      'display_name' => 'Maria',
      'profile_url' => '/u/maria',
      'avatar' => NULL,
      'member_since' => '2021',
    ], $identity->toArray());
    $this->assertSame(['user:42', 'profile:7'], $identity->getCacheTags());
    $this->assertSame(['user.permissions'], $identity->getCacheContexts());
    $this->assertSame(-1, $identity->getCacheMaxAge());
  }

  public function testResolverProduzApelidoEDataDaContaEmPerfilPublicoSemLink(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('42');
    $account->method('getRoles')->willReturn(['authenticated']);
    $account->method('getCacheTags')->willReturn(['user:42']);
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheMaxAge')->willReturn(-1);
    $account->method('getCreatedTime')->willReturn(1609459200);

    $nickname = $this->createMock(FieldItemListInterface::class);
    $nickname->method('__get')->with('value')->willReturn('Maria');
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('hasField')->willReturnCallback(static fn (string $field): bool => in_array($field, ['field_nickname', 'field_perfil_publico'], TRUE));
    $public = $this->createMock(FieldItemListInterface::class);
    $public->method('__get')->with('value')->willReturn(TRUE);
    $profile->method('get')->willReturnCallback(static fn (string $field): FieldItemListInterface => $field === 'field_nickname' ? $nickname : $public);
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
    $dateFormatter = $this->createMock(DateFormatterInterface::class);
    $dateFormatter->expects(self::once())
      ->method('format')
      ->with(1609459200, 'custom', 'Y', 'UTC')
      ->willReturn('2021');
    $resolver = new AuthorIdentityResolver(
      $entityTypeManager,
      $profileLink,
      $imageRenderer,
      new PublicProfilePolicy(),
      $dateFormatter,
    );
    $identity = $resolver->resolve($account, 'author_forum');

    $this->assertInstanceOf(AuthorIdentity::class, $identity);
    $this->assertSame([
      'variant' => 'author_forum',
      'display_name' => 'Maria',
      'profile_url' => NULL,
      'avatar' => NULL,
      'member_since' => '2021',
    ], $identity->toArray());
    $this->assertSame(['user:42', 'profile:7'], $identity->getCacheTags());
  }

  public function testContaPrivadaNaoExpoeAnoDeCriacao(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('42');
    $account->method('getRoles')->willReturn(['authenticated']);
    $account->method('getCacheTags')->willReturn(['user:42']);
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheMaxAge')->willReturn(-1);
    $account->method('getCreatedTime')->willReturn(1609459200);

    $nickname = $this->createMock(FieldItemListInterface::class);
    $nickname->method('__get')->with('value')->willReturn('Maria');
    $public = $this->createMock(FieldItemListInterface::class);
    $public->method('__get')->with('value')->willReturn(FALSE);
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('hasField')->willReturnCallback(static fn (string $field): bool => in_array($field, ['field_nickname', 'field_perfil_publico'], TRUE));
    $profile->method('get')->willReturnCallback(static fn (string $field): FieldItemListInterface => $field === 'field_nickname' ? $nickname : $public);
    $profile->method('getCacheTags')->willReturn(['profile:7']);
    $profile->method('getCacheContexts')->willReturn([]);
    $profile->method('getCacheMaxAge')->willReturn(-1);
    $storage = $this->createMock(ProfileStorageInterface::class);
    $storage->method('loadByUser')->willReturn($profile);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('profile')->willReturn($storage);
    $dateFormatter = $this->createMock(DateFormatterInterface::class);
    $dateFormatter->expects(self::never())->method('format');

    $resolver = new AuthorIdentityResolver(
      $entityTypeManager,
      $this->createMock(PublicProfileLinkInterface::class),
      new ImageRenderer(new ImageCatalog(), $entityTypeManager),
      new PublicProfilePolicy(),
      $dateFormatter,
    );
    $identity = $resolver->resolve($account, 'author_forum');

    self::assertInstanceOf(AuthorIdentity::class, $identity);
    self::assertNull($identity->toArray()['member_since']);
  }

  /**
   * Garante que outras variantes não recebam a data de criação da conta.
   */
  public function testVariantesNaoRelacionadasAoForumNaoRecebemAno(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('42');
    $account->method('getRoles')->willReturn(['authenticated']);
    $account->method('getCacheTags')->willReturn(['user:42']);
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheMaxAge')->willReturn(-1);
    $account->method('getCreatedTime')->willReturn(1609459200);

    $nickname = $this->createMock(FieldItemListInterface::class);
    $nickname->method('__get')->with('value')->willReturn('Maria');
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('hasField')->willReturnCallback(static fn (string $field): bool => $field === 'field_nickname');
    $profile->method('get')->with('field_nickname')->willReturn($nickname);
    $profile->method('getCacheTags')->willReturn(['profile:7']);
    $profile->method('getCacheContexts')->willReturn([]);
    $profile->method('getCacheMaxAge')->willReturn(-1);
    $storage = $this->createMock(ProfileStorageInterface::class);
    $storage->method('loadByUser')->willReturn($profile);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('profile')->willReturn($storage);
    $dateFormatter = $this->createMock(DateFormatterInterface::class);
    $dateFormatter->expects(self::never())->method('format');

    $resolver = new AuthorIdentityResolver(
      $entityTypeManager,
      $this->createMock(PublicProfileLinkInterface::class),
      new ImageRenderer(new ImageCatalog(), $entityTypeManager),
      new PublicProfilePolicy(),
      $dateFormatter,
    );
    $identity = $resolver->resolve($account, 'author_comment');

    self::assertInstanceOf(AuthorIdentity::class, $identity);
    self::assertNull($identity->toArray()['member_since']);
  }

  public function testLegacyAuthorNaoPodeSerConvertidoEmUser(): void {
    $resolver = new AuthorIdentityResolver(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(PublicProfileLinkInterface::class),
      new ImageRenderer(new ImageCatalog(), $this->createMock(EntityTypeManagerInterface::class)),
      new \Drupal\aculta_user\PublicProfilePolicy(),
      $this->createMock(DateFormatterInterface::class),
    );

    $this->expectException(\InvalidArgumentException::class);
    $resolver->resolve($this->createMock(UserInterface::class), 'author_growroom_historico');
  }

}

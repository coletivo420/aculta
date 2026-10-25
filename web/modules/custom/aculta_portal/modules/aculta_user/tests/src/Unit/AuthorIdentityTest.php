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
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\aculta_user\Social\SocialNetworks;
use Drupal\aculta_user\Social\SocialNetworksInterface;
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

  /**
   * Verifies the scalar cacheable identity view model.
   */
  public function testIdentityViewModelIsNeutral(): void {
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
      'bio' => NULL,
      'social_links' => [],
    ], $identity->toArray());
    $this->assertSame(['user:42', 'profile:7'], $identity->getCacheTags());
    $this->assertSame(['user.permissions'], $identity->getCacheContexts());
    $this->assertSame(-1, $identity->getCacheMaxAge());
  }

  /**
   * Resolves a public profile nickname and account year without a link.
   */
  public function testResolverReturnsNicknameAndAccountYear(): void {
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
    $profile->method('hasField')->willReturnCallback(
      static fn (string $field): bool => in_array($field, ['field_nickname', 'field_perfil_publico'], TRUE),
    );
    $public = $this->createMock(FieldItemListInterface::class);
    $public->method('__get')->with('value')->willReturn(TRUE);
    $profile->method('get')->willReturnCallback(
      static fn (string $field): FieldItemListInterface => $field === 'field_nickname' ? $nickname : $public,
    );
    $profile->method('getCacheTags')->willReturn(['profile:7']);
    $profile->method('getCacheContexts')->willReturn([]);
    $profile->method('getCacheMaxAge')->willReturn(-1);

    $storage = $this->createMock(ProfileStorageInterface::class);
    $storage->method('loadByUser')->willReturnCallback(
      static fn (UserInterface $user, string $type): ?ProfileInterface => $type === 'authenticated' ? $profile : NULL,
    );
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
      new SocialNetworks(),
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
      'bio' => NULL,
      'social_links' => [],
    ], $identity->toArray());
    $this->assertSame(['user:42', 'profile:7'], $identity->getCacheTags());
  }

  /**
   * Confirms private profiles do not expose account age.
   */
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
    $profile->method('hasField')->willReturnCallback(
      static fn (string $field): bool => in_array($field, ['field_nickname', 'field_perfil_publico'], TRUE),
    );
    $profile->method('get')->willReturnCallback(
      static fn (string $field): FieldItemListInterface => $field === 'field_nickname' ? $nickname : $public,
    );
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
      new SocialNetworks(),
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
      new SocialNetworks(),
      $dateFormatter,
    );
    $identity = $resolver->resolve($account, 'author_comment');

    self::assertInstanceOf(AuthorIdentity::class, $identity);
    self::assertNull($identity->toArray()['member_since']);
  }

  /**
   * Checks the editorial card exposes opted-in public biography and networks.
   */
  public function testEditorialCardShowsOptedInBiographyAndNetworks(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('getDisplayName')->willReturn('Joana Cultura');
    $account->method('getRoles')->willReturn(['authenticated']);
    $account->method('getCacheTags')->willReturn(['user:42']);
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheMaxAge')->willReturn(-1);

    $fields = [
      'field_nickname' => $this->field('Joana Cultura'),
      'field_perfil_publico' => $this->field(TRUE),
      'field_exibir_bio' => $this->field(TRUE),
      'field_exibir_redes' => $this->field(TRUE),
      'field_bio' => $this->field('Articuladora cultural.'),
    ];
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('hasField')->willReturnCallback(static fn (string $field): bool => isset($fields[$field]));
    $profile->method('get')->willReturnCallback(static fn (string $field): FieldItemListInterface => $fields[$field]);
    $profile->method('getCacheTags')->willReturn(['profile:7']);
    $profile->method('getCacheContexts')->willReturn([]);
    $profile->method('getCacheMaxAge')->willReturn(-1);
    $storage = $this->createMock(ProfileStorageInterface::class);
    $storage->method('loadByUser')->willReturn($profile);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('profile')->willReturn($storage);
    $networks = $this->createMock(SocialNetworksInterface::class);
    $networks->method('linksOf')->with($profile)->willReturn(['instagram' => 'https://instagram.com/joana']);
    $resolver = new AuthorIdentityResolver(
      $entityTypeManager,
      $this->createMock(PublicProfileLinkInterface::class),
      new ImageRenderer(new ImageCatalog(), $entityTypeManager),
      new PublicProfilePolicy(),
      $networks,
      $this->createMock(DateFormatterInterface::class),
    );

    $build = (new AuthorCardBuilder($resolver))->build($account, 'author_editorial', [], 'Autora');

    self::assertSame('Joana Cultura', $build['#display_name']);
    self::assertSame('Autora', $build['#designation']);
    self::assertFalse($build['#has_avatar']);
    self::assertSame('Articuladora cultural.', $build['#bio']);
    self::assertSame([
      [
        'network' => 'instagram',
        'label' => 'Instagram',
        'url' => 'https://instagram.com/joana',
      ],
    ], $build['#social_links']);
    self::assertSame(['user:42', 'profile:7'], $build['#cache']['tags']);
  }

  /**
   * Uses display name as editorial attribution when the nickname is absent.
   */
  public function testCartaoEditorialSemApelidoUsaNomeDeExibicaoSemLink(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('getDisplayName')->willReturn('Autora Revista');
    $account->method('getRoles')->willReturn(['authenticated']);
    $account->method('getCacheTags')->willReturn(['user:42']);
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheMaxAge')->willReturn(-1);
    $storage = $this->createMock(ProfileStorageInterface::class);
    $storage->method('loadByUser')->willReturn(NULL);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('profile')->willReturn($storage);
    $resolver = new AuthorIdentityResolver(
      $entityTypeManager,
      $this->createMock(PublicProfileLinkInterface::class),
      new ImageRenderer(new ImageCatalog(), $entityTypeManager),
      new PublicProfilePolicy(),
      new SocialNetworks(),
      $this->createMock(DateFormatterInterface::class),
    );

    $build = (new AuthorCardBuilder($resolver))->build($account, 'author_editorial');

    self::assertSame('Autora Revista', $build['#display_name']);
    self::assertNull($build['#profile_url']);
    self::assertFalse($build['#has_avatar']);
    self::assertNull($build['#bio']);
    self::assertSame([], $build['#social_links']);
  }

  /**
   * Keeps private profile biography and networks out of the editorial card.
   */
  public function testPerfilPrivadoNaoExpoeBioOuRedesNoCartaoEditorial(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('getDisplayName')->willReturn('Joana Cultura');
    $account->method('getRoles')->willReturn(['authenticated']);
    $account->method('getCacheTags')->willReturn(['user:42']);
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheMaxAge')->willReturn(-1);
    $fields = [
      'field_nickname' => $this->field('Joana Cultura'),
      'field_perfil_publico' => $this->field(FALSE),
      'field_exibir_bio' => $this->field(TRUE),
      'field_exibir_redes' => $this->field(TRUE),
      'field_bio' => $this->field('Bio privada.'),
    ];
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('hasField')->willReturnCallback(static fn (string $field): bool => isset($fields[$field]));
    $profile->method('get')->willReturnCallback(static fn (string $field): FieldItemListInterface => $fields[$field]);
    $profile->method('getCacheTags')->willReturn(['profile:7']);
    $profile->method('getCacheContexts')->willReturn([]);
    $profile->method('getCacheMaxAge')->willReturn(-1);
    $storage = $this->createMock(ProfileStorageInterface::class);
    $storage->method('loadByUser')->willReturn($profile);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('profile')->willReturn($storage);
    $networks = $this->createMock(SocialNetworksInterface::class);
    $networks->expects(self::never())->method('linksOf');
    $resolver = new AuthorIdentityResolver(
      $entityTypeManager,
      $this->createMock(PublicProfileLinkInterface::class),
      new ImageRenderer(new ImageCatalog(), $entityTypeManager),
      new PublicProfilePolicy(),
      $networks,
      $this->createMock(DateFormatterInterface::class),
    );

    $identity = $resolver->resolve($account, 'author_editorial');

    self::assertInstanceOf(AuthorIdentity::class, $identity);
    self::assertNull($identity->toArray()['bio']);
    self::assertSame([], $identity->toArray()['social_links']);
  }

  /**
   * Creates a field list mock containing a single field value.
   */
  private function field(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('value')->willReturn($value);
    $field->method('isEmpty')->willReturn($value === NULL || $value === '');
    return $field;
  }

  /**
   * Prevents historical authors from being resolved as active User accounts.
   */
  public function testLegacyAuthorNaoPodeSerConvertidoEmUser(): void {
    $resolver = new AuthorIdentityResolver(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(PublicProfileLinkInterface::class),
      new ImageRenderer(new ImageCatalog(), $this->createMock(EntityTypeManagerInterface::class)),
      new PublicProfilePolicy(),
      new SocialNetworks(),
      $this->createMock(DateFormatterInterface::class),
    );

    $this->expectException(\InvalidArgumentException::class);
    $resolver->resolve($this->createMock(UserInterface::class), 'author_growroom_historico');
  }

}

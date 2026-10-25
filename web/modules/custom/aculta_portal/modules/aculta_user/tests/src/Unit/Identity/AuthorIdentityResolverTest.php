<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Identity;

use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\aculta_user\Social\SocialNetworksInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\ProfileStorageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the minimal public identity contract used by member search.
 */
#[CoversClass(AuthorIdentityResolver::class)]
#[Group('aculta_user')]
final class AuthorIdentityResolverTest extends UnitTestCase {

  /**
   * Private profiles expose the approved nickname, not an internal name.
   */
  public function testPublicDisplayNameUsesNicknameForPrivateProfile(): void {
    [$resolver, $account] = $this->resolver('apelido-publico', FALSE);
    $account->expects(self::never())->method('getDisplayName');

    self::assertSame('apelido-publico', $resolver->publicDisplayName($account));
  }

  /**
   * A profile without a nickname has no public member identity.
   */
  public function testPublicDisplayNameDoesNotFallbackToInternalName(): void {
    [$resolver, $account] = $this->resolver('', TRUE);
    $account->expects(self::never())->method('getDisplayName');

    self::assertNull($resolver->publicDisplayName($account));
  }

  /**
   * Creates a resolver with one principal profile and no public avatar fields.
   *
   * @return array{0: AuthorIdentityResolver, 1: UserInterface}
   *   The resolver and its account fixture.
   */
  private function resolver(string $nicknameValue, bool $profilePublic): array {
    $account = $this->createMock(UserInterface::class);
    $account->method('getRoles')->willReturn([UserInterface::AUTHENTICATED_ROLE]);
    $account->method('id')->willReturn('42');
    $account->method('hasField')->willReturn(FALSE);
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheTags')->willReturn([]);
    $account->method('getCacheMaxAge')->willReturn(-1);

    $nickname = $this->field($nicknameValue);
    $public = $this->field($profilePublic);
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('getCacheContexts')->willReturn([]);
    $profile->method('getCacheTags')->willReturn([]);
    $profile->method('getCacheMaxAge')->willReturn(-1);
    $profile->method('hasField')->willReturnCallback(
      static fn(string $field): bool => in_array($field, ['field_nickname', 'field_perfil_publico'], TRUE),
    );
    $profile->method('get')->willReturnCallback(static fn(string $field): FieldItemListInterface => match ($field) {
      'field_nickname' => $nickname,
      'field_perfil_publico' => $public,
      default => throw new \InvalidArgumentException($field),
    });

    $profiles = $this->createMock(ProfileStorageInterface::class);
    $profiles->method('loadByUser')->willReturnCallback(static fn(UserInterface $user, string $bundle): ?ProfileInterface => $bundle === 'authenticated' ? $profile : NULL);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->with('profile')->willReturn($profiles);
    $profileLink = $this->createMock(PublicProfileLinkInterface::class);
    $profileLink->method('build')->willReturn(NULL);

    return [
      new AuthorIdentityResolver(
        $entityTypes,
        $profileLink,
        new ImageRenderer(new ImageCatalog(), $entityTypes),
        new PublicProfilePolicy(),
        $this->createMock(SocialNetworksInterface::class),
        $this->createMock(DateFormatterInterface::class),
      ),
      $account,
    ];
  }

  /**
   * Creates a single field item fixture.
   */
  private function field(mixed $value): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn($value === '');
    $field->method('__get')->with('value')->willReturn($value);
    return $field;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Avatar;

use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_user\Avatar\AvatarPool;
use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contrato do avatar único: sem foto liberada e sem acervo, não há imagem.
 */
#[Group('aculta_user')]
class AvatarResolverTest extends UnitTestCase {

  /**
   * Sem foto liberada e sem acervo, o resolver devolve NULL; nenhum chamador
   * inventa a inicial.
   */
  public function testReturnsNullWithoutPhotoOrPool(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheTags')->willReturn(['user:42']);
    $account->method('getCacheMaxAge')->willReturn(-1);
    $account->method('hasField')->with('user_picture')->willReturn(FALSE);

    $resolver = new AvatarResolver(
      new ImageRenderer(new ImageCatalog(), $this->createMock(EntityTypeManagerInterface::class)),
    );

    $cacheability = new CacheableMetadata();
    self::assertNull($resolver->build($account, 'Ana', TRUE, $cacheability));
    self::assertNull($resolver->build(NULL, 'Ana', FALSE, $cacheability));
  }

  /**
   * A selected institutional avatar remains the same across public consumers.
   */
  public function testSelectedPoolAvatarIsVisibleWithoutPersonalPhotoOptIn(): void {
    $account = $this->accountWithPicture(AvatarPool::DIRECTORY . '/avatar-17.png');
    $resolver = new AvatarResolver(
      new ImageRenderer(new ImageCatalog(), $this->createMock(EntityTypeManagerInterface::class)),
    );

    $cacheability = new CacheableMetadata();
    $build = $resolver->build($account, 'Ana', FALSE, $cacheability);

    self::assertSame('image_style', $build['#theme']);
    self::assertSame(AvatarPool::DIRECTORY . '/avatar-17.png', $build['#uri']);
    self::assertContains('user:42', $cacheability->getCacheTags());
  }

  /**
   * A personal upload stays private when public-photo opt-in is off.
   */
  public function testPersonalPhotoRemainsHiddenWithoutOptIn(): void {
    $account = $this->accountWithPicture('public://pictures/membro/foto.jpg');
    $resolver = new AvatarResolver(
      new ImageRenderer(new ImageCatalog(), $this->createMock(EntityTypeManagerInterface::class)),
    );

    self::assertNull($resolver->build($account, 'Ana', FALSE, new CacheableMetadata()));
  }

  /**
   * Creates a User double with a saved picture file.
   */
  private function accountWithPicture(string $uri): UserInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn($uri);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheTags')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('__get')->with('entity')->willReturn($file);
    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('42');
    $account->method('getCacheContexts')->willReturn([]);
    $account->method('getCacheTags')->willReturn(['user:42']);
    $account->method('getCacheMaxAge')->willReturn(-1);
    $account->method('hasField')->with('user_picture')->willReturn(TRUE);
    $account->method('get')->with('user_picture')->willReturn($field);
    return $account;
  }

}

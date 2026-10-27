<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Avatar;

use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contrato do avatar único: sem foto liberada e sem acervo, não há imagem.
 */
#[Group('aculta_user')]
class AvatarResolverTest extends UnitTestCase {

  /**
   * Sem foto liberada e sem acervo, o resolver devolve NULL (o chamador mostra a inicial).
   */
  public function testReturnsNullWithoutPhotoOrPool(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('hasField')->with('user_picture')->willReturn(FALSE);

    $resolver = new AvatarResolver(
      new ImageRenderer(new ImageCatalog(), $this->createMock(EntityTypeManagerInterface::class)),
    );

    $cacheability = new CacheableMetadata();
    self::assertNull($resolver->build($account, 'Ana', TRUE, $cacheability));
    self::assertNull($resolver->build(NULL, 'Ana', FALSE, $cacheability));
  }

}

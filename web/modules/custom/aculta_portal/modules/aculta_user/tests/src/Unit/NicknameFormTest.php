<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Account\NicknamePolicy;
use Drupal\aculta_user\Form\NicknameForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Only the public nickname is accepted by the independent Studio editor.
 */
#[CoversClass(NicknameForm::class)]
#[Group('aculta_user')]
final class NicknameFormTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Reserved, empty and invalid nicknames are rejected before querying.
   */
  public function testRejectsInvalidNicknames(): void {
    foreach (['', 'admin', 'com espaço', 'ab', 'um_acento_é'] as $invalid) {
      $entities = $this->createMock(EntityTypeManagerInterface::class);
      $entities->expects(self::never())->method('getStorage');
      $state = $this->createMock(FormStateInterface::class);
      $state->method('getValue')->with('nickname')->willReturn($invalid);
      $state->expects(self::once())->method('setErrorByName')->with('nickname', self::anything());
      $current = $this->createMock(AccountProxyInterface::class);
      $form = [];
      (new NicknameForm($entities, $current, new NicknamePolicy($entities)))->validateForm($form, $state);
    }
  }

  /**
   * Another member using the same nickname triggers a form error.
   */
  public function testDuplicateNicknamesAreRejected(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn(1);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('profile')->willReturn($storage);
    $current = $this->createMock(AccountProxyInterface::class);
    $current->method('id')->willReturn('7');
    $state = $this->createMock(FormStateInterface::class);
    $state->method('getValue')->with('nickname')->willReturn('Pirata');
    $state->expects(self::once())->method('setErrorByName')->with('nickname', self::anything());
    $form = [];
    (new NicknameForm($entities, $current, new NicknamePolicy($entities)))->validateForm($form, $state);
  }

}

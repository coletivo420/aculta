<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\aculta_user\Avatar\AvatarPool;
use Drupal\aculta_user\Form\UserPictureForm;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Remoção da foto personalizada: sorteio do avatar do sistema e mensagem ao usuário.
 */
#[Group('aculta_user')]
class UserPictureFormRemovePersonalTest extends UnitTestCase {

  /**
   * Monta o formulário com a conta atual e o pool de avatares simulados.
   */
  private function form(AvatarPool $pool, MessengerInterface $messenger): UserPictureForm {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn(7);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($user);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->willReturn($storage);

    $current = $this->createMock(AccountInterface::class);
    $current->method('id')->willReturn(7);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(static fn (string $t, array $a = []): string => strtr($t, $a));

    $container = new ContainerBuilder();
    $container->set('messenger', $messenger);
    $container->set('string_translation', $translation);
    \Drupal::setContainer($container);

    return new UserPictureForm(
      $entityTypes,
      $current,
      $this->createMock(UserDataInterface::class),
      $this->createMock(CsrfTokenGenerator::class),
      $pool,
      // Classes final não podem ser duplicadas; removePersonal() não as usa.
      (new \ReflectionClass(ImageRenderer::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(ConsumerUploadPolicy::class))->newInstanceWithoutConstructor(),
    );
  }

  /**
   * Sorteio sem avatar disponível: erro ao usuário e volta ao painel.
   */
  public function testSemAvatarSorteadoMostraErro(): void {
    $pool = $this->createMock(AvatarPool::class);
    $pool->method('reroll')->willReturn(NULL);

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addError');
    $messenger->expects($this->never())->method('addStatus');

    $state = $this->createMock(FormStateInterface::class);
    $state->expects($this->once())->method('setRedirect')->with('aculta_portal.dashboard');

    $form = [];
    $this->form($pool, $messenger)->removePersonal($form, $state);
  }

  /**
   * Sorteio ok: mensagem de sucesso e volta ao painel.
   */
  public function testSorteioOkMostraStatus(): void {
    $pool = $this->createMock(AvatarPool::class);
    $pool->method('reroll')->willReturn('avatar-03');

    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->never())->method('addError');
    $messenger->expects($this->once())->method('addStatus');

    $state = $this->createMock(FormStateInterface::class);
    $state->expects($this->once())->method('setRedirect')->with('aculta_portal.dashboard');

    $form = [];
    $this->form($pool, $messenger)->removePersonal($form, $state);
  }

}

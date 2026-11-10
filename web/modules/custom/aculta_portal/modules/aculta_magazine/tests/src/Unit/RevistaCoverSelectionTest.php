<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\aculta_file_uploader\RecentUploads;
use Drupal\aculta_magazine\Hook\RevistaCoverStudioHooks;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group as TestGroup;

/**
 * Regressão: a capa escolhida no widget da biblioteca de mídia.
 *
 * O widget grava a escolha em field_media_image[selection] até o
 * massageFormValues. A validação não podia exigir só target_id.
 */
#[TestGroup('aculta_magazine')]
final class RevistaCoverSelectionTest extends UnitTestCase {

  private const FIELD_NAME = 'field_media_image';

  /**
   * Provides the translation service the hook uses through Drupal.
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Monta o hook com dependências simuladas.
   */
  private function hook(): RevistaCoverStudioHooks {
    return new RevistaCoverStudioHooks(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
      // Classes finais: sem construtor, pois validateCover não as usa sem escolha do Studio.
      (new \ReflectionClass(RecentUploads::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(ConsumerUploadPolicy::class))->newInstanceWithoutConstructor(),
    );
  }

  /**
   * Aceita a capa quando a escolha está só em selection.
   */
  public function testSelectionOnlyMediaIsAcceptedAndWrittenToField(): void {
    $form_state = new FormState();
    $form_state->setValue(['field_media_image', 'selection'], [['target_id' => 277, 'weight' => 0]]);
    $form_state->setValue('studio_cover', 0);

    $form = [];
    $this->hook()->validateCover($form, $form_state);

    self::assertFalse($form_state->hasAnyErrors(), 'A escolha em selection não deve gerar erro.');
    self::assertSame(
      [['target_id' => 277, 'weight' => 0]],
      $form_state->getValue([self::FIELD_NAME, 'selection']),
      'massageFormValues lê selection; sem ela a mídia vira [] e o campo fica nulo.',
    );
    self::assertSame(277, (int) $form_state->getValue([self::FIELD_NAME, 0, 'target_id']));
  }

  /**
   * Continua recusando quando não há capa nem escolha do Studio.
   */
  public function testNoCoverAndNoStudioPickFailsValidation(): void {
    $form_state = new FormState();
    $form_state->setValue(['field_media_image', 'selection'], []);
    $form_state->setValue('studio_cover', 0);

    $form = [];
    $this->hook()->validateCover($form, $form_state);

    self::assertTrue($form_state->hasAnyErrors());
  }

}

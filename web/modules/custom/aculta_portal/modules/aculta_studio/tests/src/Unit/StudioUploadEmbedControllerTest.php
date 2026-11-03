<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Controller\StudioUploadEmbedController;
use Drupal\aculta_studio\Form\StudioUploadEmbedForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cobre a página de envio: integrações da lista e 404 para o resto.
 */
#[CoversClass(StudioUploadEmbedController::class)]
#[Group('aculta_studio')]
final class StudioUploadEmbedControllerTest extends UnitTestCase {

  /**
   * Integração da lista monta o formulário dela.
   */
  public function testKnownIntegrationBuildsItsForm(): void {
    $builder = $this->createMock(FormBuilderInterface::class);
    $builder->expects(self::once())->method('getForm')
      ->with(StudioUploadEmbedForm::class, 'wiki')
      ->willReturn(['#form_id' => 'aculta_studio_upload_embed_form']);

    $page = (new StudioUploadEmbedController($builder))->page('wiki');

    self::assertSame('Wiki', $page['#title']);
    self::assertSame('aculta_studio_upload_embed_form', $page['form']['#form_id']);
  }

  /**
   * Usuário (foto do perfil) não tem página no Studio.
   */
  public function testIntegrationOutsideTheListIsNotFound(): void {
    $builder = $this->createMock(FormBuilderInterface::class);
    $builder->expects(self::never())->method('getForm');

    $this->expectException(NotFoundHttpException::class);
    (new StudioUploadEmbedController($builder))->page('usuario');
  }

}

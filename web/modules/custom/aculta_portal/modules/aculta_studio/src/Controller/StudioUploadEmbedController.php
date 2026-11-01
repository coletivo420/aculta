<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_studio\Form\StudioUploadEmbedForm;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Página de envio do Studio: a drop zone de uma integração, na casca do Studio.
 */
final class StudioUploadEmbedController {

  public function __construct(
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  /**
   * Monta o formulário de envio da integração, ou responde 404 se não existir.
   */
  public function page(string $integracao): array {
    if (!isset(StudioUploadEmbedForm::INTEGRACOES[$integracao])) {
      throw new NotFoundHttpException();
    }
    return [
      '#title' => StudioUploadEmbedForm::INTEGRACOES[$integracao],
      'form' => $this->formBuilder->getForm(StudioUploadEmbedForm::class, $integracao),
    ];
  }

}

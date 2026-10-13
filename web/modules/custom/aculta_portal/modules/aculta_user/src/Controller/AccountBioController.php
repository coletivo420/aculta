<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\aculta_user\Form\AccountBioForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Edição da bio só por modal. Visita direta à rota volta para a visão geral; não existe página de edição.
 */
final class AccountBioController extends ControllerBase {

  public function __construct(
    private readonly FormBuilderInterface $formBuilderService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('form_builder'));
  }

  /**
   * Formulário da bio dentro do modal. Requisição comum (sem modal nem AJAX) é redirecionada.
   */
  public function modal(Request $request): array|RedirectResponse {
    $isModal = $request->isXmlHttpRequest() || $request->query->get('_wrapper_format') === 'drupal_modal';
    if (!$isModal) {
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }
    return [
      '#type' => 'container',
      'form' => $this->formBuilderService->getForm(AccountBioForm::class),
    ];
  }

}

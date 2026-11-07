<?php

declare(strict_types=1);

namespace Drupal\aculta_paths\Controller;

use Drupal\aculta_paths\Form\SurfaceEditForm;
use Drupal\aculta_portal\Domain\DomainSurfaceMap;
use Drupal\aculta_portal\Domain\SurfaceConfigReader;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Abre a edição de uma superfície em modal (AJAX) ou em página (fallback).
 */
final class SurfaceEditController extends ControllerBase {

  /**
   *
   */
  public static function create(ContainerInterface $container): static {
    return new static();
  }

  /**
   * Rota aculta_paths.editar.
   */
  public function abrir(string $purpose, Request $request): Response|array {
    if (!array_key_exists($purpose, DomainSurfaceMap::DOMAIN_IDS)) {
      throw new NotFoundHttpException();
    }
    $titulo = SurfaceConfigReader::TITULOS[$purpose] ?? $purpose;
    $form = $this->formBuilder()->getForm(SurfaceEditForm::class, $purpose);
    if (!$request->isXmlHttpRequest()) {
      return $form;
    }
    $resposta = new AjaxResponse();
    $resposta->addCommand(new OpenModalDialogCommand(
      (string) $this->t('Editar @titulo', ['@titulo' => $titulo]),
      $form,
      ['width' => 640],
    ));
    return $resposta;
  }

}

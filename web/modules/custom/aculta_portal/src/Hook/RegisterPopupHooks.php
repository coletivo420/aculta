<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Form\RegisterPopupForm;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registra o cadastro em modal como handler de formulário da entidade de usuário (operação "register_popup").
 *
 * Com o handler registrado, o Core monta o formulário pelo construtor de formulários de entidade (com as dependências
 * injetadas), como faz para o cadastro comum (operação "register").
 */
final class RegisterPopupHooks {

  #[Hook('entity_type_alter')]
  public function registrarFormularioPopup(array &$entity_types): void {
    if (isset($entity_types['user'])) {
      $entity_types['user']->setFormClass('register_popup', RegisterPopupForm::class);
    }
  }

}

<?php

declare(strict_types=1);

namespace Drupal\aculta_id_cpf\Service;

use Drupal\Core\Form\FormStateInterface;

/**
 * Adapts CPF identifiers before the existing Drupal password authentication.
 */
final class CpfLoginFormCallbacks {

  public function __construct(
    private readonly CpfLoginIdentifierResolver $resolver,
  ) {}

  /**
   * Temporarily replaces a CPF with its account name for native authentication.
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $formState
   *   Value used for $formState.
   *
   * @return void
   *   Value returned by the method.
   */
  public function resolveIdentifier(array &$form, FormStateInterface $formState): void {
    $identifier = $formState->getValue('name');
    $passValue = $formState->getValue('pass');
    $passStr = is_scalar($passValue) ? (string) $passValue : '';
    if (!is_string($identifier) || $identifier === ''
      || trim($passStr) === '') {
      return;
    }

    $username = $this->resolver->resolveUsername($identifier);
    if ($username === NULL) {
      return;
    }

    $formState->setValue('name', $username);
  }

  /**
   * Restores the submitted identifier after native authentication validation.
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $formState
   *   Value used for $formState.
   *
   * @return void
   *   Value returned by the method.
   */
  public function restoreIdentifier(array &$form, FormStateInterface $formState): void {
    $input = $formState->getUserInput();
    $identifier = $input['name'] ?? NULL;
    if (is_string($identifier)) {
      $formState->setValue('name', $identifier);
    }
  }

}

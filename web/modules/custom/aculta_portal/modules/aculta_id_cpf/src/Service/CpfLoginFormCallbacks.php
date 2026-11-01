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
   */
  public function resolveIdentifier(array &$form, FormStateInterface $formState): void {
    $identifier = $formState->getValue('name');
    if (!is_string($identifier) || $identifier === ''
      || trim((string) $formState->getValue('pass')) === '') {
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
   */
  public function restoreIdentifier(array &$form, FormStateInterface $formState): void {
    $input = $formState->getUserInput();
    $identifier = $input['name'] ?? NULL;
    if (is_string($identifier)) {
      $formState->setValue('name', $identifier);
    }
  }

}

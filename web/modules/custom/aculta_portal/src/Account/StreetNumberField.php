<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\address\Element\Address;
use Drupal\Core\Form\FormStateInterface;

/**
 * Campo Número dentro do elemento de endereço (checkout e Meus Dados).
 *
 * O número fica no logradouro do perfil ("Avenida Paulista, 1000"). No formulário, o campo Número é um subcampo do
 * próprio endereço, logo depois do Logradouro. Por isso ele é recriado junto com o endereço no rebuild AJAX do CEP
 * (sem perder o valor). É criado no #process, depois de Address::processAddress, porque os subcampos só existem
 * nesse momento. Os processos padrão do endereço são mantidos.
 */
final class StreetNumberField {

  public static function attach(array &$element, string $default): void {
    $element['#process'] = [
      [Address::class, 'processAddress'],
      [Address::class, 'processGroup'],
      [self::class, 'processStreetNumber'],
    ];
    $element['#street_number_default'] = $default;
  }

  /** Recursivo: aplica ao primeiro elemento de endereço encontrado na árvore. */
  public static function attachTo(array &$tree, string $default): bool {
    if (($tree['#type'] ?? NULL) === 'address') {
      self::attach($tree, $default);
      return TRUE;
    }
    foreach ($tree as $key => &$child) {
      if (is_array($child) && !str_starts_with((string) $key, '#') && self::attachTo($child, $default)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /** #process: cria o campo Número depois do Logradouro. */
  public static function processStreetNumber(array $element, FormStateInterface $form_state, array &$complete_form): array {
    if (isset($element['address_line1'])) {
      $element['street_number'] = [
        '#type' => 'textfield',
        '#title' => t('Número'),
        '#required' => TRUE,
        '#maxlength' => 20,
        '#default_value' => $element['#street_number_default'] ?? '',
        '#weight' => ($element['address_line1']['#weight'] ?? 0) + 0.5,
      ];
    }
    return $element;
  }

  /** Elemento processado de Número dentro da árvore (com #parents), ou NULL. */
  public static function find(array $tree): ?array {
    if (isset($tree['street_number']) && is_array($tree['street_number']) && isset($tree['street_number']['#parents'])) {
      return $tree['street_number'];
    }
    foreach ($tree as $key => $child) {
      if (is_array($child) && !str_starts_with((string) $key, '#')) {
        $found = self::find($child);
        if ($found !== NULL) {
          return $found;
        }
      }
    }
    return NULL;
  }

}

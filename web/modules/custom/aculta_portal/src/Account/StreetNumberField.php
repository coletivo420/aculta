<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\address\Element\Address;
use Drupal\Core\Form\FormStateInterface;

/**
 * Número do endereço (checkout de cobrança, Meus dados e livro de endereços).
 *
 * Armazenamento: o número fica no logradouro do perfil ("Avenida Paulista, 1000"). Sem número, o logradouro
 * termina em "S/N" ("Avenida Paulista, S/N"). Aceita de 1 a 10 dígitos. O campo é um subcampo do elemento de endereço
 * (criado no #process, depois de Address::processAddress), por isso é recriado junto com o endereço no rebuild AJAX
 * do CEP. A regra de leitura e gravação fica nos métodos split() e join(), para todos os formulários usarem a mesma.
 */
final class StreetNumberField {

  public const SN = 'S/N';

  /** Limite folgado de dígitos do número (a caixa é pequena, mas aceita números maiores). */
  public const MAX_DIGITS = 10;

  /** Separa o logradouro salvo em rua, número e marca S/N. */
  public static function split(string $line): array {
    if (!preg_match('/^(.*),\s*([^,]+)$/', $line, $m)) {
      return ['street' => trim($line), 'number' => '', 'sn' => FALSE];
    }
    $tail = trim($m[2]);
    if (strcasecmp($tail, self::SN) === 0) {
      return ['street' => trim($m[1]), 'number' => '', 'sn' => TRUE];
    }
    return ['street' => trim($m[1]), 'number' => $tail, 'sn' => FALSE];
  }

  /** Junta rua e número (ou S/N) no formato armazenado. */
  public static function join(string $street, string $number, bool $sn): string {
    $street = trim($street);
    if ($sn) {
      return $street . ', ' . self::SN;
    }
    $number = trim($number);
    return $number === '' ? $street : $street . ', ' . $number;
  }

  public static function attach(array &$element, string $number, bool $sn): void {
    $element['#process'] = [
      [Address::class, 'processAddress'],
      [Address::class, 'processGroup'],
      [self::class, 'processStreetNumber'],
    ];
    $element['#street_number_default'] = $number;
    $element['#street_number_sn_default'] = $sn;
  }

  /** Recursivo: aplica ao primeiro elemento de endereço encontrado na árvore. */
  public static function attachTo(array &$tree, string $number, bool $sn): bool {
    if (($tree['#type'] ?? NULL) === 'address') {
      self::attach($tree, $number, $sn);
      return TRUE;
    }
    foreach ($tree as $key => &$child) {
      if (is_array($child) && !str_starts_with((string) $key, '#') && self::attachTo($child, $number, $sn)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /** #process: cria o campo Número e a marca S/N depois do Logradouro. */
  public static function processStreetNumber(array $element, FormStateInterface $form_state, array &$complete_form): array {
    if (isset($element['address_line1'])) {
      $weight = ($element['address_line1']['#weight'] ?? 0);
      $element['street_number'] = [
        '#type' => 'textfield',
        '#title' => t('Número'),
        '#required' => TRUE,
        '#maxlength' => self::MAX_DIGITS,
        '#size' => 4,
        '#attributes' => [
          'inputmode' => 'numeric',
          'pattern' => '[0-9]{1,' . self::MAX_DIGITS . '}',
          'autocomplete' => 'off',
          'data-aculta-number' => 'true',
        ],
        '#default_value' => $element['#street_number_default'] ?? '',
        '#element_validate' => [[self::class, 'validateNumber']],
        '#weight' => $weight + 0.5,
        '#wrapper_attributes' => ['class' => ['portal-street-number']],
      ];
      $element['street_number_sn'] = [
        '#type' => 'checkbox',
        '#title' => t('S/N'),
        '#title_display' => 'after',
        '#default_value' => (bool) ($element['#street_number_sn_default'] ?? FALSE),
        '#attributes' => ['data-aculta-number-sn' => 'true'],
        '#weight' => $weight + 0.6,
        '#wrapper_attributes' => ['class' => ['portal-street-number-sn']],
      ];
    }
    return $element;
  }

  /** Validação no servidor: 1 a 4 dígitos, ou S/N (sem número, dispensa a obrigatoriedade). */
  public static function validateNumber(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $sn_parents = array_merge(array_slice($element['#parents'], 0, -1), ['street_number_sn']);
    if ((bool) $form_state->getValue($sn_parents)) {
      $form_state->setValue($element['#parents'], '');
      return;
    }
    $value = trim((string) $form_state->getValue($element['#parents']));
    if ($value === '') {
      $form_state->setError($element, t('Preencha o número ou marque S/N (sem número).'));
      return;
    }
    if (!preg_match('/^[0-9]{1,' . self::MAX_DIGITS . '}$/', $value)) {
      $form_state->setError($element, t('O número deve ter de 1 a @max dígitos.', ['@max' => self::MAX_DIGITS]));
    }
  }

  /** Valores do formulário de endereço: [número, S/N] (parents relativos ao elemento de endereço). */
  public static function values(array $tree, FormStateInterface $form_state): array {
    $number = self::find($tree);
    if ($number === NULL) {
      return ['', FALSE];
    }
    $sn_parents = array_merge(array_slice($number['#parents'], 0, -1), ['street_number_sn']);
    return [trim((string) $form_state->getValue($number['#parents'])), (bool) $form_state->getValue($sn_parents)];
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

<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formulário de endereço único do site.
 *
 * É o mesmo formulário de Minha conta > Endereço, usado também no checkout (cobrança), no cadastro da loja e nos
 * perfis de equipe. O tratamento fica aqui: país fixo em Brasil (sem seletor), rótulos em português, Número e S/N
 * (StreetNumberField) e a biblioteca do CEP. Os dados continuam em cada entidade (perfil do cliente, perfil de equipe,
 * loja); só o formulário é compartilhado. Ver docs/portal/ACCOUNT-ADDRESS.md.
 */
final class AccountAddressForm {

  /** Biblioteca do CEP (módulo aculta_portal_cep). */
  public const LIBRARY = 'aculta_portal_cep/cep-address';

  /** Perfis de equipe (ver PerfilDoPapel) que editam o endereço pelo painel administrativo. */
  public const TEAM_PROFILE_TYPES = ['administrator', 'associado', 'gerente_loja', 'lms_teacher'];

  /** Rótulos do formulário de endereço, por chave do elemento. */
  private const LABELS = [
    'address_line1' => 'Endereço / Logradouro',
    'address_line2' => 'Complemento',
    'locality' => 'Cidade',
    'dependent_locality' => 'Bairro',
    'administrative_area' => 'Estado',
    'postal_code' => 'CEP',
    'sorting_code' => 'Código postal',
  ];

  /**
   * Campo de endereço do formulário da entidade, ou NULL quando a entidade não usa o endereço compartilhado.
   *
   * Loja (commerce_store) usa o campo `address`. Perfil de equipe usa `field_address`.
   */
  public static function fieldOf(EntityInterface $entity): ?string {
    if ($entity->getEntityTypeId() === 'commerce_store') {
      return 'address';
    }
    if ($entity->getEntityTypeId() === 'profile' && in_array($entity->bundle(), self::TEAM_PROFILE_TYPES, TRUE)) {
      return 'field_address';
    }
    return NULL;
  }

  /**
   * Aplica o formulário de endereço a cada elemento de endereço da árvore. Retorna TRUE quando encontrou um.
   *
   * @param array<string, mixed> $tree
   */
  public static function apply(array &$tree, string $number, bool $sn): bool {
    if (($tree['#type'] ?? NULL) === 'address') {
      $tree['#default_value']['country_code'] ??= 'BR';
      StreetNumberField::attach($tree, $number, $sn);
      $tree['#process'][] = [self::class, 'finish'];
      return TRUE;
    }
    foreach ($tree as $key => &$child) {
      if (is_array($child) && !str_starts_with((string) $key, '#') && self::apply($child, $number, $sn)) {
        unset($child);
        return TRUE;
      }
    }
    unset($child);
    return FALSE;
  }

  /**
   * Número e S/N do endereço salvo, para o formulário ao abrir. Lê do armazenamento, não do formulário em edição.
   *
   * @return array{street: string, number: string, sn: bool}
   */
  public static function savedParts(?EntityInterface $saved, string $field): array {
    if ($saved === NULL || !$saved->hasField($field) || $saved->get($field)->isEmpty()) {
      return ['street' => '', 'number' => '', 'sn' => FALSE];
    }
    return StreetNumberField::split((string) $saved->get($field)->first()->getAddressLine1());
  }

  /**
   * Entity builder: junta rua e número (ou S/N) no logradouro antes do save.
   *
   * @param array<string, mixed> $form
   */
  public static function buildStreetNumber(string $entityTypeId, EntityInterface $entity, array &$form, FormStateInterface $formState): void {
    // Perfil (Minha conta, checkout e equipe) usa field_address; a loja usa address.
    $field = $entity->hasField('field_address') ? 'field_address' : 'address';
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return;
    }
    [$number, $sn] = StreetNumberField::values($form, $formState);
    $address = $entity->get($field)->first();
    $value = $address->getValue();
    $value['address_line1'] = StreetNumberField::join((string) $address->getAddressLine1(), $number, $sn);
    $address->setValue($value);
  }

  /**
   * Process callback final: país fixo em Brasil e rótulos em português.
   *
   * Roda depois do processamento do endereço (Address::processAddress), quando os subcampos já existem.
   *
   * @param array<string, mixed> $element
   *
   * @return array<string, mixed>
   */
  public static function finish(array $element): array {
    if (isset($element['country_code'])) {
      // Campo oculto, e não #access FALSE: o módulo Address descarta toda a entrada do formulário quando o
      // country_code não vem no POST, e a lista de cidades passa a usar o endereço salvo (ver o teste de CEP).
      $element['country_code']['#type'] = 'hidden';
      unset($element['country_code']['#options'], $element['country_code']['#ajax'], $element['country_code']['#empty_value']);
      $element['country_code']['#default_value'] = 'BR';
      $element['country_code']['#value'] = 'BR';
    }
    foreach (self::LABELS as $key => $label) {
      if (isset($element[$key])) {
        $element[$key]['#title'] = t($label);
      }
    }
    return $element;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\address\Element\Address;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Formulário de endereço único do site.
 *
 * É o mesmo formulário de Minha conta > Endereço, checkout, loja, equipe e
 * locais de evento. O tratamento fica aqui: país fixo em Brasil, rótulos em
 * português, Número/S/N e biblioteca CEP. Os dados continuam nas respectivas
 * entidades; só o formulário é compartilhado. Ver ACCOUNT-ADDRESS.md.
 */
final class AccountAddressForm {

  /**
   * Biblioteca do CEP (módulo aculta_portal_cep).
   */
  public const LIBRARY = 'aculta_portal_cep/cep-address';

  /**
   * Perfis de equipe que editam endereço pelo painel administrativo.
   */
  public const TEAM_PROFILE_TYPES = ['administrator', 'associado', 'gerente_loja', 'lms_teacher'];

  /**
   * Rótulos do formulário de endereço, por chave do elemento.
   */
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
   * Obtém o campo de endereço da entidade que usa o formulário compartilhado.
   *
   * Loja usa `address`, equipe usa `field_address` e evento usa
   * `field_endereco`.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entidade cujo campo é identificado.
   *
   * @return string|null
   *   Nome do campo ou NULL quando não há mapeamento.
   */
  public static function fieldOf(EntityInterface $entity): ?string {
    if ($entity->getEntityTypeId() === 'commerce_store') {
      return 'address';
    }
    if ($entity->getEntityTypeId() === 'aculta_event_location') {
      return 'field_endereco';
    }
    if ($entity->getEntityTypeId() === 'profile'
      && in_array($entity->bundle(), self::TEAM_PROFILE_TYPES, TRUE)) {
      return 'field_address';
    }
    return NULL;
  }

  /**
   * Aplica o formulário central ao primeiro elemento address encontrado.
   *
   * @param array<string, mixed> $tree
   *   Árvore do formulário que contém o elemento address.
   * @param string $number
   *   Número salvo para preencher no elemento.
   * @param bool $sn
   *   TRUE quando o endereço salvo é sem número.
   * @param bool $withStreetNumber
   *   TRUE para incluir os controles e validação de número da Conta.
   *
   * @return bool
   *   TRUE quando um elemento address foi encontrado e alterado.
   */
  public static function apply(
    array &$tree,
    string $number = '',
    bool $sn = FALSE,
    bool $withStreetNumber = TRUE,
  ): bool {
    if (($tree['#type'] ?? NULL) === 'address') {
      $tree['#default_value']['country_code'] = 'BR';
      $tree['#available_countries'] = ['BR'];
      $tree['#field_overrides'] = array_merge($tree['#field_overrides'] ?? [], [
        'givenName' => 'hidden',
        'familyName' => 'hidden',
        'additionalName' => 'hidden',
        'organization' => 'hidden',
        'addressLine3' => 'hidden',
      ]);
      $tree['#attached']['library'][] = self::LIBRARY;
      $tree['#element_validate'][] = [self::class, 'validateAddressElement'];
      if ($withStreetNumber) {
        StreetNumberField::attach($tree, $number, $sn);
      }
      else {
        // External address adapters (such as OSM tag review) have their own
        // number vocabulary; they still share the Address widget and CEP
        // lookup without applying the account's numeric-only number rule.
        $tree['#process'] = [
          [Address::class, 'processAddress'],
          [Address::class, 'processGroup'],
        ];
      }
      $tree['#process'][] = [self::class, 'finish'];
      return TRUE;
    }
    foreach ($tree as $key => &$child) {
      if (is_array($child) && !str_starts_with((string) $key, '#')
        && self::apply($child, $number, $sn, $withStreetNumber)) {
        unset($child);
        return TRUE;
      }
    }
    unset($child);
    return FALSE;
  }

  /**
   * Obtém Número e S/N salvos para abrir o formulário.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $saved
   *   Entidade persistida que contém o endereço.
   * @param string $field
   *   Campo de endereço lido.
   *
   * @return array{street: string, number: string, sn: bool}
   *   Rua, número e indicador de sem número.
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
   * @param string $entityTypeId
   *   Tipo da entidade salva.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entidade que recebe o endereço.
   * @param array<string, mixed> $form
   *   Formulário submetido.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Estado do formulário submetido.
   */
  public static function buildStreetNumber(
    string $entityTypeId,
    EntityInterface $entity,
    array &$form,
    FormStateInterface $formState,
  ): void {
    // Perfis usam field_address; eventos, field_endereco; lojas, address.
    $field = $entity->hasField('field_address') ? 'field_address'
      : ($entity->hasField('field_endereco') ? 'field_endereco' : 'address');
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return;
    }
    [$number, $sn] = StreetNumberField::values($form, $formState);
    $address = $entity->get($field)->first();
    $value = $address->getValue();
    $value['country_code'] = 'BR';
    $value['address_line1'] = StreetNumberField::join(
      (string) $address->getAddressLine1(),
      $number,
      $sn,
    );
    $address->setValue($value);
  }

  /**
   * Process callback final: país fixo em Brasil e rótulos em português.
   *
   * Roda após Address::processAddress, quando os subcampos já existem.
   *
   * @param array<string, mixed> $element
   *   Elemento address após o processamento de seus subcampos.
   *
   * @return array<string, mixed>
   *   Elemento atualizado com país fixo e rótulos em português.
   */
  public static function finish(array $element): array {
    if (isset($element['country_code'])) {
      // Campo oculto, e não #access FALSE: o módulo Address descarta a entrada
      // toda quando country_code não vem no POST; ver o teste de CEP.
      $element['country_code']['#type'] = 'hidden';
      unset(
        $element['country_code']['#options'],
        $element['country_code']['#ajax'],
        $element['country_code']['#empty_value'],
      );
      $element['country_code']['#default_value'] = 'BR';
      $element['country_code']['#value'] = 'BR';
      $element['country_code']['#element_validate'] = [
        [self::class, 'validateCountryCode'],
      ];
      $element['country_code']['#weight'] = -100;
    }

    // Oculta campos de nome de pessoa física e linhas extras de complemento.
    // Nome e sobrenome pertencem ao perfil do usuário ou não se aplicam (locais, lojas).
    // Complemento possui apenas uma linha canônica (address_line2).
    foreach (['given_name', 'family_name', 'additional_name', 'organization', 'address_line3'] as $hiddenKey) {
      if (isset($element[$hiddenKey])) {
        $element[$hiddenKey]['#access'] = FALSE;
      }
    }

    // O CEP é sempre o primeiro campo visível no formulário de endereço
    // para permitir o autopreenchimento imediato de rua, bairro, cidade e UF.
    if (isset($element['postal_code'])) {
      $element['postal_code']['#weight'] = -50;
    }
    if (isset($element['address_line1'])) {
      $element['address_line1']['#weight'] = -40;
    }
    if (isset($element['street_number'])) {
      $element['street_number']['#weight'] = -35;
    }
    if (isset($element['street_number_sn'])) {
      $element['street_number_sn']['#weight'] = -34;
    }
    if (isset($element['address_line2'])) {
      $element['address_line2']['#weight'] = -30;
    }
    if (isset($element['address_line3'])) {
      $element['address_line3']['#weight'] = -25;
    }
    if (isset($element['dependent_locality'])) {
      $element['dependent_locality']['#weight'] = -20;
    }
    foreach (\Drupal\Core\Render\Element::children($element) as $key) {
      if (str_starts_with((string) $key, 'container')) {
        $element[$key]['#weight'] = -10;
      }
    }
    if (isset($element['locality'])) {
      $element['locality']['#weight'] = -10;
    }
    if (isset($element['administrative_area'])) {
      $element['administrative_area']['#weight'] = -9;
    }
    foreach (self::LABELS as $key => $label) {
      if (isset($element[$key])) {
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString -- labels are fixed constants above.
        $element[$key]['#title'] = new TranslatableMarkup($label);
      }
    }
    return $element;
  }

  /**
   * Finaliza elemento de país avulso para campo oculto fixo em BR.
   *
   * @param array<string, mixed> $element
   *   Elemento address_country sendo processado.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Estado do formulário.
   * @param array<string, mixed> $completeForm
   *   Estrutura do formulário completo.
   *
   * @return array<string, mixed>
   *   Elemento modificado.
   */
  public static function finishCountryElement(array &$element, FormStateInterface $formState, array &$completeForm): array {
    $element['#type'] = 'hidden';
    $element['#value'] = 'BR';
    $element['#default_value'] = 'BR';
    if (isset($element['country_code'])) {
      $element['country_code']['#type'] = 'hidden';
      $element['country_code']['#value'] = 'BR';
      $element['country_code']['#default_value'] = 'BR';
      unset(
        $element['country_code']['#options'],
        $element['country_code']['#ajax'],
        $element['country_code']['#empty_value'],
      );
    }
    unset(
      $element['#options'],
      $element['#ajax'],
      $element['#empty_value'],
    );
    $element['#element_validate'] = [
      [self::class, 'validateCountryCode'],
    ];
    return $element;
  }

  /**
   * Validação no servidor: o país deve ser exclusivamente Brasil (BR).
   *
   * @param array<string, mixed> $element
   *   Elemento country_code validado.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Estado do formulário.
   * @param array<string, mixed> $completeForm
   *   Formulário completo.
   */
  public static function validateCountryCode(array &$element, FormStateInterface $formState, array &$completeForm): void {
    $value = $formState->getValue($element['#parents']);
    if ($value === NULL || $value === '') {
      $formState->setValue($element['#parents'], 'BR');
      return;
    }
    if ($value !== 'BR') {
      $formState->setError($element, new TranslatableMarkup('O país de endereço deve ser exclusivamente Brasil (BR).'));
    }
  }

  /**
   * Validação no servidor do elemento de endereço: garante country_code BR.
   *
   * @param array<string, mixed> $element
   *   Elemento address.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Estado do formulário.
   * @param array<string, mixed> $completeForm
   *   Formulário completo.
   */
  public static function validateAddressElement(array &$element, FormStateInterface $formState, array &$completeForm): void {
    $values = $formState->getValue($element['#parents']);
    if (!is_array($values)) {
      return;
    }
    $country = $values['country_code'] ?? NULL;
    if ($country === NULL || $country === '') {
      $parents = array_merge($element['#parents'], ['country_code']);
      $formState->setValue($parents, 'BR');
      return;
    }
    if ($country !== 'BR') {
      $formState->setError($element, new TranslatableMarkup('O país de endereço deve ser exclusivamente Brasil (BR).'));
    }
  }

}

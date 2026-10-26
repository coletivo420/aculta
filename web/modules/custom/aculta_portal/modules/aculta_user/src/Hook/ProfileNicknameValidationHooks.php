<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_user\Account\AccountNames;
use Drupal\aculta_user\Account\NicknamePolicy;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Validates nickname edits made through the legacy Profile Form API.
 */
final class ProfileNicknameValidationHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.nickname_policy')]
    private readonly NicknamePolicy $nicknamePolicy,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Adds the User-owned nickname policy to role Profile entity forms.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }
    $profile = $form_object->getEntity();
    if (!$profile instanceof ProfileInterface
      || !PerfilDoPapel::ehTipoDePapel($profile)
      || !$profile->hasField('field_nickname')
      || !isset($form['field_nickname'])) {
      return;
    }

    $form['#validate'][] = [$this, 'validateNickname'];
  }

  /**
   * Rejects invalid or already claimed nicknames in Profile forms.
   */
  public function validateNickname(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }
    $profile = $form_object->getEntity();
    if (!$profile instanceof ProfileInterface || !PerfilDoPapel::ehTipoDePapel($profile)) {
      return;
    }

    $submitted = $form_state->getValue('field_nickname');
    $nickname = trim((string) (is_array($submitted) ? ($submitted[0]['value'] ?? '') : $submitted));
    $existing_values = $profile->get('field_nickname')->getValue();
    $existing = trim((string) ($existing_values[0]['value'] ?? ''));
    if ($nickname === $existing || $nickname === '') {
      return;
    }

    if (!AccountNames::isValid($nickname)) {
      $form_state->setErrorByName('field_nickname', $this->translation->translate('Use um apelido de 3 a 40 caracteres, com letras sem acento, números ou hífen, que não seja reservado.'));
      return;
    }
    if ($this->nicknamePolicy->isTaken($nickname, (int) $profile->getOwnerId())) {
      $form_state->setErrorByName('field_nickname', $this->translation->translate('Este apelido já está em uso.'));
      return;
    }

    if (is_array($submitted) && isset($submitted[0]) && is_array($submitted[0])) {
      $submitted[0]['value'] = $nickname;
      $form_state->setValue('field_nickname', $submitted);
    }
    elseif (is_string($submitted)) {
      $form_state->setValue('field_nickname', $nickname);
    }
  }

}

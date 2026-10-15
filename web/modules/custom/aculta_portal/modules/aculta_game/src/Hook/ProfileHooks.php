<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Hook;

use Drupal\aculta_game\AchievementPresentation;
use Drupal\aculta_game\Form\AchievementDisplayForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Integra Game aos pontos de extensão neutros do User. */
final class ProfileHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'aculta_game.achievement_presentation')]
    private readonly AchievementPresentation $presentation,
    private readonly FormBuilderInterface $forms,
  ) {}

  #[Hook('aculta_user_public_profile_alter')]
  public function publicProfile(array &$build, ProfileInterface $profile): void {
    $build['conquistas'] = $this->presentation->publicFor($profile);
  }

  #[Hook('aculta_user_activity_content_alter')]
  public function activity(array &$build, string $tab, int $uid): void {
    if ($tab === 'conquistas') {
      $build = [
        'conquistas' => $this->presentation->own($uid),
        'preferences' => $this->forms->getForm(AchievementDisplayForm::class),
      ];
    }
  }

  #[Hook('form_aculta_user_profile_visibility_form_alter')]
  public function privacy(array &$form, FormStateInterface $formState): void {
    $form['conquistas'] = [
      '#type' => 'details',
      '#title' => $this->t('Conquistas'),
      '#weight' => 20,
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Escolher conquistas públicas e destaques'),
        '#url' => Url::fromRoute('aculta_user.atividade', ['aba' => 'conquistas']),
      ],
    ];
  }

}

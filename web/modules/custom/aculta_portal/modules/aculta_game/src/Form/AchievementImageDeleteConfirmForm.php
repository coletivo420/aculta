<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Form;

use Drupal\aculta_game\AchievementPresentation;
use Drupal\aculta_user\Badge\AchievementDefinitions;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Confirms removal of the current image before unlinking/deleting its file. */
final class AchievementImageDeleteConfirmForm extends ConfirmFormBase {

  private ?string $achievementId = NULL;

  public function __construct(
    private readonly AchievementDefinitions $definitions,
    private readonly AchievementPresentation $presentation,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_user.achievement_definitions'), $container->get('aculta_game.achievement_presentation'));
  }

  public function getFormId(): string {
    return 'aculta_game_achievement_image_delete_confirm';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $conquista = NULL): array {
    $this->achievementId = $conquista;
    $definition = $conquista !== NULL ? ($this->definitions->all()[$conquista] ?? NULL) : NULL;
    if ($definition === NULL || $this->presentation->imageForDefinition($definition) === []) {
      throw new NotFoundHttpException();
    }
    $form_state->set('achievement_id', $conquista);
    $form['preview'] = $this->presentation->imageForDefinition($definition);
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): string {
    $definition = $this->achievementId !== NULL ? ($this->definitions->all()[$this->achievementId] ?? NULL) : NULL;
    return (string) $this->t('Remover a imagem da conquista “@label”?', ['@label' => $definition['label'] ?? '']);
  }

  public function getDescription(): string {
    return (string) $this->t('A imagem será retirada desta conquista. Se for um arquivo enviado, ele será apagado do servidor quando nenhuma outra área do site o utilizar. Esta ação não pode ser desfeita.');
  }

  public function getConfirmText(): string {
    return (string) $this->t('Confirmar remoção da imagem');
  }

  public function getCancelText(): string {
    return (string) $this->t('Voltar sem remover');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('aculta_game.achievement_edit', ['conquista' => $this->achievementId]);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = (string) $form_state->get('achievement_id');
    $this->definitions->removeImage($id);
    $this->messenger()->addStatus($this->t('Imagem removida da conquista.'));
    $form_state->setRedirect('aculta_game.achievement_edit', ['conquista' => $id]);
  }

}

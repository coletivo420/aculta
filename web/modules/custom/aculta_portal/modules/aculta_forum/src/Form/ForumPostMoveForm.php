<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Service\ForumPostMover;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Confirms moving a public Forum post (and its descendant replies) to another topic.
 */
final class ForumPostMoveForm extends FormBase {

  public function __construct(
    protected readonly ForumPostMover $mover,
    protected readonly AccountInterface $account,
    protected readonly RouteMatchInterface $postRoute,
  ) {}

  /**
   * Creates an instance from the service container.
   *
   * @param ContainerInterface $container
   *   Dependency injection container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_forum.post_mover'),
      $container->get('current_user'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_post_move';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   * @param ?ForumPost $aculta_forum_post
   *   The post selected for the move.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ForumPost $aculta_forum_post = NULL): array {
    if (!$aculta_forum_post instanceof ForumPost || !$this->mover->access($aculta_forum_post, $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $options = $this->mover->destinations($aculta_forum_post, $this->account);
    if ($options === []) {
      throw new AccessDeniedHttpException('No accessible open public Forum topic is available.');
    }

    $form_state->set('post_id', (int) $aculta_forum_post->id());
    $source = $aculta_forum_post->get('topic')->entity;
    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Publicação selecionada'),
      '#plain_text' => $this->t('Publicação #@id em “@topic”. Ao mover, as respostas descendentes desta publicação também serão movidas.', [
        '@id' => (string) $aculta_forum_post->id(),
        '@topic' => $source ? (string) $source->label() : '',
      ]),
    ];
    $form['preservation'] = [
      '#type' => 'item',
      '#plain_text' => $this->t('Autoria, conteúdo, data, estado de moderação, IDs, revisões e relações entre respostas serão preservados. Links antigos para a publicação no tópico de origem não são redirecionados.'),
    ];
    $form['destination'] = [
      '#type' => 'select',
      '#title' => $this->t('Tópico público de destino'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['confirm_move'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirmo mover a publicação e todas as respostas descendentes para o tópico indicado.'),
      '#required' => TRUE,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Mover publicação'),
        '#button_type' => 'primary',
      ],
      'cancel' => [
        '#type' => 'portal_button',
        '#props' => [
          'text' => (string) $this->t('Cancelar'),
          'url' => $this->returnUrl()->toString(),
          'variant' => 'secondary',
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $destination_id = filter_var($form_state->getValue('destination'), FILTER_VALIDATE_INT);
    if ($destination_id === FALSE || $destination_id <= 0 || !$form_state->getValue('confirm_move')) {
      $form_state->setErrorByName('destination', $this->t('Selecione um destino e confirme a movimentação.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $post_id = filter_var($form_state->get('post_id'), FILTER_VALIDATE_INT);
    $destination_id = filter_var($form_state->getValue('destination'), FILTER_VALIDATE_INT);
    if ($post_id === FALSE || $destination_id === FALSE) {
      $form_state->setErrorByName('destination', $this->t('Identificador inválido.'));
      return;
    }
    try {
      $count = $this->mover->move((int) $post_id, (int) $destination_id, $this->account);
    }
    catch (\InvalidArgumentException $exception) {
      $form_state->setErrorByName('destination', $this->t('A movimentação não foi realizada: @reason', [
        '@reason' => $exception->getMessage(),
      ]));
      return;
    }
    catch (\Throwable) {
      $this->messenger()->addError($this->t('Não foi possível mover a publicação. Nenhuma alteração foi mantida; tente novamente ou encaminhe a operação à equipe técnica.'));
      return;
    }

    $this->messenger()->addStatus($this->formatPlural($count, '1 publicação foi movida.', '@count publicações foram movidas.'));
    $form_state->setRedirectUrl($this->returnUrl());
  }

  /**
   * Volta para a fila da Marley quando a ação veio da rota Marley; senão, ao admin do Fórum.
   */
  private function returnUrl(): Url {
    $routeName = $this->postRoute->getRouteName();
    if (is_string($routeName) && str_starts_with($routeName, 'aculta_forum.marley_post_')) {
      return Url::fromRoute('aculta_cgu.forum_marley_queue');
    }
    return Url::fromRoute('aculta_forum.admin');
  }

}

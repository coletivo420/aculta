<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Service\ForumReadMarker;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms marking all currently accessible public Forum topics as read.
 */
final class ForumMarkAllReadForm extends ConfirmFormBase {

  public function __construct(
    private readonly ForumReadMarker $readMarker,
    private readonly AccountProxyInterface $currentUser,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_forum.read_marker'),
      $container->get('current_user'),
      $container->get('aculta_portal.domain_purpose'),
    );
  }

  /**
   * {@inheritdoc} */
  public function getFormId(): string {
    return 'aculta_forum_mark_all_read';
  }

  /**
   * {@inheritdoc} */
  public function getQuestion(): string {
    return (string) $this->t('Marcar todas as discussões públicas acessíveis como lidas?');
  }

  /**
   * {@inheritdoc} */
  public function getDescription(): string {
    return (string) $this->t('Esta ação atualiza somente seu histórico de leitura. Tópicos e comentários não serão alterados.');
  }

  /**
   * {@inheritdoc} */
  public function getConfirmText(): string {
    return (string) $this->t('Marcar tudo como lido');
  }

  /**
   * {@inheritdoc} */
  public function getCancelUrl(): Url {
    return $this->forumTopicsUrl();
  }

  /**
   * {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $total = $this->readMarker->countEligibleTopics();
    if ($total === 0) {
      $this->messenger()->addStatus($this->t('Não há discussões públicas acessíveis para marcar.'));
      $form_state->setRedirectUrl($this->forumTopicsUrl());
      return;
    }

    batch_set([
      'title' => $this->t('Atualizando seu histórico de leitura'),
      'operations' => [[
        'aculta_forum.read_marker:markBatch',
        [(int) $this->currentUser->id(), $total],
      ],
      ],
      'progress_message' => $this->t('Processando discussões públicas acessíveis.'),
      'error_message' => $this->t('O processamento do histórico de leitura foi interrompido.'),
      'finished' => 'aculta_forum.read_marker:finishBatch',
    ]);
    $form_state->setRedirectUrl($this->forumTopicsUrl());
  }

  /**
   * Returns the public topic list on the Forum purpose domain. */
  private function forumTopicsUrl(): Url {
    return $this->domainPurposeManager->pathUrl('forum', '/')
      ?? throw new \UnexpectedValueException('O domínio do Fórum não está configurado.');
  }

}

<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Form;

use Drupal\aculta_image\ImageCleanup;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tela de execução manual das rotinas automáticas seguras de limpeza. */
final class ImageCleanupForm extends ConfirmFormBase {

  public function __construct(private readonly ImageCleanup $cleanup) {}

  /**
   * Creates the object with its injected services.
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_image.cleanup'));
  }

  /**
   * Returns the form identifier.
   */
  public function getFormId(): string {
    return 'aculta_image_cleanup_form';
  }

  /**
   * Returns the confirmation question.
   */
  public function getQuestion(): string {
    return (string) $this->t('Executar limpeza segura de imagens?');
  }

  /**
   * Returns the confirmation description.
   */
  public function getDescription(): array|string {
    return (string) $this->t('Serão reavaliadas até 100 imagens substituídas/removidas e imagens temporárias com mais de @age horas. Arquivos com referência em file_usage ou entity_usage permanecem.', ['@age' => round($this->cleanup->temporaryAge() / 3600, 1)]);
  }

  /**
   * Returns the confirmation button label.
   */
  public function getConfirmText(): string {
    return (string) $this->t('Executar limpeza');
  }

  /**
   * Returns the cancellation destination.
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('aculta_image.audit');
  }

  /**
   * Handles the form submission.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $result = $this->cleanup->run(100);
    $this->messenger()->addStatus($this->t('Limpeza concluída: @deleted arquivo(s) removido(s); @processed item(ns) de substituição verificado(s).', [
      '@deleted' => $result['deleted'],
      '@processed' => $result['processed'],
    ]));
    $form_state->setRedirect('aculta_image.audit');
  }

}

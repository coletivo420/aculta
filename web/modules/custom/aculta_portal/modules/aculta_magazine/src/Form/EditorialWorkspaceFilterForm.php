<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form API filters for the Studio publication inventory. */
final class EditorialWorkspaceFilterForm extends FormBase {

  public function __construct(private readonly EntityTypeManagerInterface $entities, private readonly LanguageManagerInterface $languages) {}

  /**
   * Creates the inventory filter form from Drupal entity and language services.
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'), $container->get('language_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_magazine_workspace_filters';
  }

  /**
   * Builds filters for the publication inventory.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título'),
      '#default_value' => $this->queryString('titulo'),
    ];
    $terms = $this->entities->getStorage('taxonomy_term')->loadByProperties(['vid' => 'editorial_category']);
    $category_options = ['' => $this->t('- Todas as categorias -')];
    foreach ($terms as $term) {
      $category_options[$term->id()] = $term->label();
    }
    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Categoria'),
      '#options' => $category_options,
      '#default_value' => $this->queryString('categoria'),
    ];
    $form['author'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Autoria ou coautoria'),
      '#description' => $this->t('Informe o nome de usuário ou UID.'),
      '#default_value' => $this->queryString('autoria'),
    ];
    $language_options = ['' => $this->t('- Todos os idiomas -')];
    foreach ($this->languages->getLanguages() as $language) {
      $language_options[$language->getId()] = $language->getName();
    }
    $form['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Idioma'),
      '#options' => $language_options,
      '#default_value' => $this->queryString('idioma'),
    ];
    $form['state'] = [
      '#type' => 'select',
      '#title' => $this->t('Estado editorial'),
      '#options' => [
        '' => $this->t('- Todos -'),
        'draft' => $this->t('Rascunho'),
        'review' => $this->t('Em revisão'),
        'published' => $this->t('Publicado'),
        'archived' => $this->t('Arquivado'),
      ],
      '#default_value' => $this->queryString('estado'),
    ];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Filtrar')];
    return $form;
  }

  /**
   * Redirects to the filtered inventory without changing content.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $query = array_filter([
      'titulo' => trim((string) $form_state->getValue('title')),
      'categoria' => $form_state->getValue('category'),
      'autoria' => trim((string) $form_state->getValue('author')),
      'idioma' => $form_state->getValue('language'),
      'estado' => $form_state->getValue('state'),
    ]);
    $form_state->setRedirect('aculta_magazine.studio_list', [], ['query' => $query]);
  }

  /**
   * Returns only scalar query values suitable for Form API defaults.
   */
  private function queryString(string $key): string {
    $value = $this->getRequest()->query->all()[$key] ?? '';
    return is_scalar($value) ? trim((string) $value) : '';
  }

}

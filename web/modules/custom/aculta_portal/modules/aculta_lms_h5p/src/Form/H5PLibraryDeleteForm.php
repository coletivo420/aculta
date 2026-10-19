<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Form;

use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PLibraryCacheInvalidator;
use Drupal\aculta_lms_h5p\Controller\H5PLibraryAdmin;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;


/**
 * Implements the H5PLibraryDeleteForm form.
 */
class H5PLibraryDeleteForm extends FormBase {

  protected H5PDrupal $framework;

  public function __construct(
    private readonly Connection $database,
    private readonly H5PLibraryCacheInvalidator $cacheInvalidator,
    H5PDrupal $framework,
  ) {
    $this->framework = $framework;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('aculta_lms_h5p.library_cache_invalidator'),
      $container->get('aculta_lms_h5p.framework'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'h5p_library_delete_form';
  }

  /**
   * Check if library is deletable
   *
   * @param string $library_id
   * @return boolean
   */
  private function isLibraryDeletable($library_id) {
    // Is library deletable ?
    $h5p_drupal = H5PDrupal::getInstance('interface');
    $notCached = $h5p_drupal->getNumNotFiltered();
    $library_usage = $h5p_drupal->getLibraryUsage($library_id, $notCached ? TRUE : FALSE);
    return ($library_usage['content'] === 0 && $library_usage['libraries'] === 0);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $library_id = NULL) {
    if ($this->isLibraryDeletable($library_id)) {
      // Create form:
      $form['library_id'] = array(
        '#type' => 'hidden',
        '#value' => $library_id
      );

      $form['info'] = array(
        '#markup' => '<div>' . $this->t('Confirma a exclusão da biblioteca H5P @library_name?', ['@library_name' => $this->database->select('h5p_libraries', 'l')->fields('l', ['title'])->condition('library_id', $library_id)->execute()->fetchField()]) . '</div>'
      );

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Excluir'),
      );
    }
    else {
      // May not delete this one
      $form['undeletable'] = [
        '#markup' => $this->t('A biblioteca está em uso por atividades ou por outras bibliotecas e não pode ser excluída.'),
      ];
    }

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $library_id = $form_state->getValue('library_id');
    if ($this->isLibraryDeletable($library_id)) {
      H5PDrupal::getInstance('core')->deleteLibrary($library_id);
      $this->cacheInvalidator->invalidate();
    }
  }
}

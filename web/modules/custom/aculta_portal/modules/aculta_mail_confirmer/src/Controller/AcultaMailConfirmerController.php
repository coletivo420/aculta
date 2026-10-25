<?php

namespace Drupal\aculta_mail_confirmer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for email confirmer module routes.
 */
class AcultaMailConfirmerController extends ControllerBase {

  /**
   * Creates the controller.
   *
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface $manager
   *   The email confirmation service.
   */
  public function __construct(protected AcultaMailConfirmerManagerInterface $manager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('aculta_mail_confirmer'));
  }

  /**
   * Resend confirmation email.
   *
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation
   *   The confirmation entity.
   */
  public function resendConfirmation(AcultaMailConfirmationInterface $confirmation) {
    try {
      if ($this->manager->sendRequest($confirmation)) {
        // Send timestamp was updated, save it.
        $confirmation->save();
        $this->messenger()->addStatus($this->t(
          'A new confirmation message has been sent to %mail',
          ['%mail' => $confirmation->getEmail()]));
      }
      else {
        $this->messenger()->addError($this->t('Unable to send email. Contact the site administrator if the problem persists.'));
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('This email confirmation is no longer valid.'));
    }

    return $this->redirect('<front>');
  }

}

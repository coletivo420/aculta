<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Form\ChangemailForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Troca de e-mail na Segurança da conta (ponto de extensão aculta_portal_changemail).
 *
 * O Portal monta a aba E-mail; este módulo entrega o formulário. Só aparece quando o envio está pronto e a
 * confirmação do novo endereço está ligada: sem ela, a troca não tem como ser feita com segurança.
 */
final class ChangemailHooks {

  public function __construct(
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'form_builder')]
    private readonly FormBuilderInterface $formBuilder,
    #[Autowire(service: 'module_handler')]
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  #[Hook('aculta_portal_changemail_alter')]
  public function securityEmailAlter(array &$section, UserInterface $account, bool $emailReady): void {
    if (!$emailReady
      || !$this->moduleHandler->moduleExists('aculta_mail_confirmer_user')
      || !$this->configFactory->get('aculta_mail_confirmer_user.settings')->get('user_email_change.enabled')) {
      return;
    }
    $section['change_form'] = $this->formBuilder->getForm(ChangemailForm::class, $account);
  }

}

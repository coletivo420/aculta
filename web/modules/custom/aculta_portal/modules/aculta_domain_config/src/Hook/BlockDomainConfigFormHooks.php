<?php

declare(strict_types=1);

namespace Drupal\aculta_domain_config\Hook;

use Drupal\block\BlockListBuilder;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderBefore;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\domain\DomainInterface;
use Drupal\domain_config_switcher\DomainConfigSwitcherInterface;
use Drupal\domain_config_ui\DomainConfigEditContextInterface;
use Drupal\domain_config_ui\DomainConfigUIManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adiciona o seletor de domínio às telas de bloco do MAIN.
 *
 * O domain_config_switcher só cobre ConfigFormBase com #config_target. O
 * layout de blocos usa EntityForm e BlockListBuilder, então esta ponte
 * reproduz o seletor mínimo e usa o mesmo DomainConfigEditContext, sem mudar
 * a negociação do domínio do request.
 */
final class BlockDomainConfigFormHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly DomainConfigSwitcherInterface $switcher,
    private readonly DomainConfigEditContextInterface $editContext,
    private readonly DomainConfigUIManagerInterface $manager,
    private readonly StorageInterface $configStorage,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Adds the domain selector before domain_config_ui builds its toggle.
   */
  #[Hook('form_alter', order: new OrderBefore(['domain_config_ui', 'domain_config_entity_ui']))]
  public function formAlter(array &$form, FormStateInterface $form_state, string $formId): void {
    $configNames = $this->blockConfigNames($form_state);
    if ($configNames === []) {
      return;
    }
    if (!$this->manager->isAllowedRoute()) {
      return;
    }
    $domains = $this->switcher->getSelectableDomains();
    if ($domains === []) {
      return;
    }

    $selected = $this->switcher->getSelectedDomainId();
    if ($selected !== NULL) {
      // Reforça o contexto para o toggle e para o salvamento do formulário.
      $this->editContext->setEditingDomain($selected, $configNames);
    }

    $options = ['' => $this->t('- Global (sem override) -')];
    foreach ($domains as $domain) {
      if ($domain instanceof DomainInterface && !$domain->isDefault()) {
        $options[$domain->id()] = $domain->label();
      }
    }
    if (count($options) === 1) {
      return;
    }

    $form['aculta_domain_config'] = [
      '#type' => 'details',
      '#title' => $this->t('Domínio do override de blocos'),
      '#open' => TRUE,
      '#weight' => -100,
      'domain' => [
        '#type' => 'select',
        '#title' => $this->t('Configurar domínio'),
        '#options' => $options,
        '#default_value' => $selected ?? '',
        '#description' => $this->t('A configuração global continua valendo para os domínios sem override.'),
      ],
      'switch' => [
        '#type' => 'submit',
        '#value' => $this->t('Alternar domínio'),
        '#submit' => [self::class . ':switchSubmit'],
        '#limit_validation_errors' => [['aculta_domain_config', 'domain']],
        '#attributes' => ['class' => ['button--small']],
      ],
    ];

    if ($selected !== NULL && isset($domains[$selected])) {
      $form['aculta_domain_config']['notice'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Editando o override de @domain. As alterações não afetam o valor global nem os demais domínios.', [
          '@domain' => $domains[$selected]->label(),
        ]),
        '#weight' => -10,
      ];
    }
  }

  /**
   * Returns the block config names this form reads or writes.
   *
   * @return string[]
   *   Nomes de configuração de bloco, ou vazio quando não é uma tela de bloco.
   */
  private function blockConfigNames(FormStateInterface $formState): array {
    $formObject = $formState->getFormObject();
    if ($formObject instanceof BlockListBuilder) {
      return $this->configStorage->listAll('block.block.');
    }
    if ($formObject instanceof EntityForm) {
      $entity = $formObject->getEntity();
      if ($entity->getEntityTypeId() === 'block') {
        return [$entity->getConfigDependencyName()];
      }
    }
    return [];
  }

  /**
   * Redirects to the current block form with the selected domain in the URL.
   */
  public function switchSubmit(array &$form, FormStateInterface $form_state): void {
    $domainId = $form_state->getValue(['aculta_domain_config', 'domain'], '');
    $query = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
    if (is_string($domainId) && $domainId !== '') {
      $query[DomainConfigSwitcherInterface::QUERY_ARG] = $domainId;
    }
    else {
      unset($query[DomainConfigSwitcherInterface::QUERY_ARG]);
    }
    $form_state->setRedirect('<current>', [], ['query' => $query]);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Slug\EventSlugGenerator;
use Drupal\aculta_user\Organization\OrganizationMembershipManager;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário para cadastro e edição de Organização Cultural (ARQ-05).
 */
final class EventOrganizationForm extends ContentEntityForm {

  /**
   * Gerenciador de membros de organização.
   */
  protected ?OrganizationMembershipManager $membershipManager = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    if ($container->has('aculta_user.organization_membership_manager')) {
      $instance->membershipManager = $container->get('aculta_user.organization_membership_manager');
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    /** @var \Drupal\aculta_events\Entity\EventOrganization $entity */
    $entity = $this->entity;

    if ($entity->isNew()) {
      $form['is_claimed_choice'] = [
        '#type' => 'radios',
        '#title' => $this->t('Tipo de cadastro da organização'),
        '#options' => [
          'unclaimed' => $this->t('Cadastrar como organização não reivindicada (coletivo público ou parceiro sem titular no site)'),
          'claimed' => $this->t('Cadastrar como minha organização (assumir a titularidade imediata)'),
        ],
        '#default_value' => 'unclaimed',
        '#weight' => -10,
        '#description' => $this->t('Organizações não reivindicadas podem ser editadas por qualquer usuário autorizado a criar eventos e reivindicadas posteriormente mediante comprovação.'),
      ];
    }
    else {
      if ($entity->isUnclaimed()) {
        $form['unclaimed_notice'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['messages', 'messages--warning']],
          '#markup' => $this->t('<strong>Atenção:</strong> Esta organização é pública e <em>não reivindicada</em>. Qualquer usuário autorizado a criar eventos pode editar suas informações até que um titular seja aprovado pela administração.'),
          '#weight' => -15,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\aculta_events\Entity\EventOrganization $entity */
    $entity = $this->entity;
    $isNew = $entity->isNew();
    $currentUid = (int) $this->currentUser()->id();

    if ($entity->get('field_slug')->isEmpty()) {
      $entity->set('field_slug', EventSlugGenerator::fromTitle((string) $entity->label()));
    }

    if ($isNew) {
      $choice = (string) $form_state->getValue('is_claimed_choice', 'unclaimed');
      if ($choice === 'claimed') {
        $entity->set('reivindicada', TRUE);
        $entity->set('reivindicacao_status', 'confirmada');
        $entity->set('titular', $currentUid);
        $entity->set('field_organizador', $currentUid);
      }
      else {
        $entity->set('reivindicada', FALSE);
        $entity->set('reivindicacao_status', 'nao_reivindicada');
        $entity->set('titular', NULL);
        $entity->set('field_organizador', $currentUid);
      }
    }

    $result = parent::save($form, $form_state);

    if ($isNew && $entity->isClaimed() && $currentUid > 0) {
      if ($this->membershipManager !== NULL) {
        $this->membershipManager->addMembership($currentUid, (string) $entity->id(), 'owner', 'approved', TRUE, TRUE);
      }
    }

    $this->messenger()->addStatus($this->t('Organização %label salva com sucesso.', ['%label' => $entity->label()]));
    $currentRoute = (string) $this->getRouteMatch()->getRouteName();
    if (str_contains($currentRoute, 'admin')) {
      $form_state->setRedirect('aculta_events.admin_organizations');
    }
    else {
      $form_state->setRedirect('aculta_events.organizer_dashboard');
    }

    return $result;
  }

}

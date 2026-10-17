<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Role\Controller;

use Drupal\aculta_portal\Role\Form\RoleHierarchyForm;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Página Papéis do Drupal (/painel-administrativo/pessoas/roles) com a seção Papéis e perfis do ACULTA.
 *
 * A lista de papéis do núcleo (criar, editar, excluir, ordenar) continua como está. A cadeia de herança e os tipos
 * de perfil entram logo abaixo, como uma seção a mais da mesma tela.
 */
final class RolesPageController implements ContainerInjectionInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
    );
  }

  /**
   * @return array<string, mixed>
   */
  public function build(): array {
    return [
      'lista_do_nucleo' => $this->entityTypeManager->getListBuilder('user_role')->render(),
      'papeis_e_perfis' => [
        '#type' => 'container',
        '#attributes' => ['id' => 'papeis-e-perfis'],
        'titulo' => ['#markup' => '<h2>' . t('Papéis e perfis') . '</h2>'],
        'formulario' => $this->formBuilder->getForm(RoleHierarchyForm::class),
      ],
    ];
  }

}

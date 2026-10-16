<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lista contas ativas sem e-mail confirmado, com ação de confirmação manual (ACULTA Portal, decisão de 2026-10-16).
 */
final class PendingEmailConfirmationController extends ControllerBase {

  /** Limite de contas listadas. */
  private const LIMIT = 200;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly EmailConfirmationPolicy $policy,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_portal.email_confirmation_policy'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Renderiza a lista.
   */
  public function list(): array {
    $storage = $this->entityTypeManagerService->getStorage('user');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('status', 1)->condition('uid', 0, '>')->sort('created', 'DESC')->range(0, self::LIMIT)->execute();
    $linhas = [];
    foreach ($storage->loadMultiple($ids) as $conta) {
      if ($this->policy->isConfirmed($conta)) {
        continue;
      }
      $linhas[] = [
        $conta->getAccountName(),
        $conta->id(),
        $this->dateFormatter->format($conta->getCreatedTime(), 'short'),
        ['data' => ['#type' => 'link', '#title' => $this->t('Confirmar e-mail'), '#url' => Url::fromRoute('aculta_portal.email_confirmar_manual', ['user' => $conta->id()])]],
      ];
    }
    return [
      '#cache' => ['tags' => ['user_list'], 'contexts' => ['user.permissions']],
      'texto' => ['#markup' => '<p>' . $this->t('Contas ativas sem e-mail confirmado. A confirmação manual marca o endereço como confirmado sem enviar e-mail.') . '</p>'],
      'tabela' => [
        '#type' => 'table',
        '#header' => [$this->t('Conta'), $this->t('uid'), $this->t('Criada em'), $this->t('Ação')],
        '#rows' => $linhas,
        '#empty' => $this->t('Nenhuma conta sem e-mail confirmado.'),
      ],
    ];
  }

}

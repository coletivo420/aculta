<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_user\Form\AccountBioForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Gravação da bio pelo modal (JSON). O JS do formulário chama esta rota, atualiza a caixa e fecha o modal.
 */
final class AccountBioSaveController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly AccountProxyInterface $currentUserService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'), $container->get('current_user'));
  }

  /**
   * Grava a bio da conta atual. Texto vazio grava vazio (a caixa volta ao aviso padrão).
   */
  public function save(Request $request): JsonResponse {
    $bio = trim((string) $request->request->get('bio', ''));
    if (mb_strlen($bio) > AccountBioForm::MAX_LENGTH) {
      return new JsonResponse(['error' => 'too_long'], 422);
    }
    $storage = $this->entityTypeManagerService->getStorage('profile');
    $account = $this->currentUserService->getAccount();
    $profile = PerfilDoPapel::obter($storage, $account);
    $profile->set('field_bio', $bio);
    $profile->save();
    return new JsonResponse(['ok' => TRUE, 'bio' => $bio]);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_user\Social\SocialNetworks;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Gravação do link de uma rede social pelo modal (JSON). O JS do formulário atualiza a página depois.
 */
final class SocialLinkSaveController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly AccountProxyInterface $currentUserService,
    private readonly SocialNetworks $networks,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'), $container->get('current_user'), $container->get('aculta_user.social_networks'));
  }

  /**
   * Grava o link da rede informada. Entrada inválida responde 422 com a mensagem.
   */
  public function save(Request $request): JsonResponse {
    $rede = (string) $request->request->get('rede', '');
    try {
      $uri = $this->networks->normalize($rede, (string) $request->request->get('link', ''));
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => 'invalid', 'message' => $e->getMessage()], 422);
    }
    $storage = $this->entityTypeManagerService->getStorage('profile');
    $account = $this->currentUserService->getAccount();
    $profile = $storage->loadByUser($account, 'participante')
      ?? $storage->create(['type' => 'participante', 'uid' => (int) $account->id()]);
    $this->networks->setLink($profile, $rede, $uri);
    $profile->save();
    return new JsonResponse(['ok' => TRUE, 'rede' => $rede, 'uri' => $uri]);
  }

}

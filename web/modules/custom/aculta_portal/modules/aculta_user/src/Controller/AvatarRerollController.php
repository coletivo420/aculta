<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\aculta_user\Avatar\AvatarPool;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Sorteia outro avatar do acervo para a própria conta (botão do popup de foto).
 *
 * Chamado por link AJAX (use-ajax) com token CSRF. Responde com a troca de todos os avatares do painel
 * (atributo data-aculta-user-avatar). Sem JavaScript, volta ao painel.
 */
final class AvatarRerollController implements ContainerInjectionInterface {

  /** Atributo que marca cada avatar exibido no painel (alvo do AJAX). */
  public const SLOT_ATTRIBUTE = 'data-aculta-user-avatar';

  public function __construct(
    private readonly AvatarPool $pool,
    private readonly RendererInterface $renderer,
    private readonly AccountInterface $currentUserService,
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_user.avatar_pool'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  /** Executa o sorteio e responde. */
  public function reroll(Request $request): AjaxResponse|RedirectResponse {
    $uid = (int) $this->currentUserService->id();
    $account = $this->entityTypeManagerService->getStorage('user')->load($uid);
    if ($account !== NULL) {
      $this->pool->reroll($account);
    }

    if (!$request->isXmlHttpRequest() && $request->query->get('_wrapper_format') !== 'drupal_ajax') {
      return new RedirectResponse(Url::fromRoute('aculta_portal.dashboard')->toString());
    }

    $response = new AjaxResponse();
    $fid = $account ? (int) ($account->get('user_picture')->target_id ?? 0) : 0;
    $file = $fid > 0 ? $this->entityTypeManagerService->getStorage('file')->load($fid) : NULL;
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [self::SLOT_ATTRIBUTE => ''],
      'image' => $file ? [
        '#theme' => 'image_style',
        '#style_name' => 'aculta_avatar',
        '#uri' => $file->getFileUri(),
        '#alt' => (string) $account->label(),
        '#attributes' => ['data-portal-ui' => 'account-avatar'],
      ] : [],
    ];
    $html = (string) $this->renderer->renderInIsolation($build);
    $response->addCommand(new ReplaceCommand('[' . self::SLOT_ATTRIBUTE . ']', $html));
    // Marca o documento: ao fechar o modal, a página recarrega (photo-flow.js) para refletir o sorteio em todo o painel.
    $response->addCommand(new InvokeCommand('body', 'attr', ['data-aculta-avatar-changed', '1']));
    return $response;
  }

}

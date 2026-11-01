<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\aculta_user\Avatar\AvatarPool;
use Drupal\Core\Ajax\AjaxResponse;
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

  /**
   * Sorteia uma prévia no modal. Não grava a foto: "Salvar foto do perfil" aplica a escolha (AvatarPool::applyPending).
   * Só o modal é atualizado; o avatar da visão geral muda quando a pessoa salva.
   */
  public function reroll(Request $request): AjaxResponse|RedirectResponse {
    $uid = (int) $this->currentUserService->id();
    $account = $this->entityTypeManagerService->getStorage('user')->load($uid);

    if (!$request->isXmlHttpRequest() && $request->query->get('_wrapper_format') !== 'drupal_ajax') {
      return new RedirectResponse(Url::fromRoute('aculta_portal.dashboard')->toString());
    }

    $response = new AjaxResponse();
    $entry = $account !== NULL ? $this->pool->preview($account) : NULL;
    if ($entry === NULL) {
      return $response;
    }
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [self::SLOT_ATTRIBUTE => ''],
      'image' => [
        '#theme' => 'image',
        '#uri' => $this->pool->previewUrl($entry),
        '#alt' => (string) $account->label(),
        '#attributes' => ['data-portal-ui' => 'account-avatar'],
      ],
    ];
    $html = (string) $this->renderer->renderInIsolation($build);
    // Só a prévia dentro do modal: o avatar da visão geral (fora do diálogo) muda ao salvar.
    $response->addCommand(new ReplaceCommand('.ui-dialog [' . self::SLOT_ATTRIBUTE . ']', $html));
    return $response;
  }

}

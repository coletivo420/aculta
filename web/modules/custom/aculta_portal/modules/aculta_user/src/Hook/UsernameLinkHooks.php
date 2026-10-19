<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\PublicProfileLink;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Nome de usuário em qualquer lugar do site aponta para o perfil público, e não para a rota de identidade do Core.
 *
 * Dois caminhos do Core ligam o nome a `user/<uid>`, que no purpose conta vira /identidade/<uid>: é a página própria
 * da conta, não o perfil de quem está sendo citado.
 * - O tema `username`, usado em comentários e nos fóruns.
 * - Campos de referência a usuário (autor, remetente, participantes de conversa), com o formatador de rótulo com link.
 * Os dois passam por PublicProfileLink, que gera o `/u/<apelido>` atual no purpose `main`. Sem perfil público, o nome
 * sai sem link.
 */
final class UsernameLinkHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.public_profile_link')]
    private readonly PublicProfileLink $publicProfileLink,
  ) {}

  #[Hook('preprocess_username')]
  public function preprocessUsername(array &$variables): void {
    $account = $variables['account'] ?? NULL;
    // Visitantes anônimos e contas que não são usuários mantêm o comportamento do Core.
    if (!$account instanceof UserInterface || (int) $account->id() === 0) {
      return;
    }

    $cache = BubbleableMetadata::createFromRenderArray($variables);
    $url = $this->publicProfileLink->build($account, $cache);
    if ($url === NULL) {
      $variables['link_path'] = NULL;
      $variables['profile_access'] = FALSE;
    }
    else {
      $variables['link_path'] = $url->toString();
      $variables['profile_access'] = TRUE;
      $variables['attributes']['href'] = $url->toString();
    }
    $cache->applyTo($variables);
  }

  #[Hook('preprocess_field')]
  public function preprocessField(array &$variables): void {
    $items = $variables['element']['#items'] ?? NULL;
    if (!$items instanceof FieldItemListInterface || $items->getFieldDefinition()->getSetting('target_type') !== 'user') {
      return;
    }

    foreach ($variables['items'] ?? [] as $delta => $item) {
      $content = $item['content'] ?? NULL;
      $user = $items->get($delta)?->entity;
      // Só o link do rótulo é trocado; itens sem link ou sem usuário carregado ficam como estão.
      if (!is_array($content) || !isset($content['#url']) || !$user instanceof UserInterface) {
        continue;
      }

      $cache = BubbleableMetadata::createFromRenderArray($content);
      $url = $this->publicProfileLink->build($user, $cache);
      if ($url === NULL) {
        $variables['items'][$delta]['content'] = ['#plain_text' => (string) ($content['#title'] ?? $user->getDisplayName())];
        $cache->applyTo($variables['items'][$delta]['content']);
        continue;
      }
      $content['#url'] = $url;
      $cache->applyTo($content);
      $variables['items'][$delta]['content'] = $content;
    }
  }

}

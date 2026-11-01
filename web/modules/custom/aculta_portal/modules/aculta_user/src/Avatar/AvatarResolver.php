<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Avatar;

use Drupal\aculta_image\ImageRenderer;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * Avatar público único do site, na mesma ordem de Conta/perfil.
 *
 * 1. foto da conta, quando o chamador informa que a política pública a libera;
 * 2. avatar ilustrativo estável do acervo (AvatarPool), que é o fallback final
 *    e obrigatório para quem não tem foto.
 *
 * Não decide visibilidade de campos: recebe $showPhoto já calculado pela
 * policy. O AvatarPool é opcional só para testes unitários; em produção é
 * sempre injetado.
 * O NULL abaixo é contrato defensivo do teste sem pool: o site não tem estado
 * "sem acervo" e a inicial do nome nunca é usada.
 */
final class AvatarResolver {

  public function __construct(
    private readonly ImageRenderer $imageRenderer,
    private readonly ?AvatarPool $avatarPool = NULL,
  ) {}

  /**
   * Monta o render array do avatar, ou NULL quando não há imagem a mostrar.
   *
   * @param \Drupal\user\UserInterface|null $account
   *   Conta cuja foto ou avatar será apresentado.
   * @param string $name
   *   Texto alternativo acessível da imagem.
   * @param bool $showPhoto
   *   Se a política do chamador autoriza mostrar foto pessoal.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Metadata compartilhada de cache da identidade.
   * @param array<string, string> $attributes
   *   Atributos extras do elemento de avatar (por exemplo, data-portal-ui).
   *
   * @return array|null
   *   Imagem cacheável ou NULL quando a fonte não está disponível.
   */
  public function build(?UserInterface $account, string $name, bool $showPhoto, CacheableMetadata $cacheability, array $attributes = []): ?array {
    // Every consumer must invalidate the rendered identity when the canonical
    // user_picture changes, including callers that only add profile cache tags.
    if ($account !== NULL) {
      $cacheability->addCacheableDependency($account);
    }

    if ($account !== NULL && $account->hasField('user_picture') && !$account->get('user_picture')->isEmpty()) {
      $file = $account->get('user_picture')->entity;
      if ($file !== NULL) {
        $uri = $file->getFileUri();
        // Avatars from the institutional pool are not personal photos. The
        // member explicitly selected them in the photo editor, so keep that
        // selection consistent in public profiles and author cards even when
        // public-photo opt-in is off. Uploaded personal photos remain private.
        $isPoolAvatar = str_starts_with($uri, AvatarPool::DIRECTORY . '/');
        if ($showPhoto || $isPoolAvatar) {
          $cacheability->addCacheableDependency($file);
          $avatar = $this->imageRenderer->build('avatar', $uri, $name, ['loading' => 'lazy']);
          if ($avatar !== NULL) {
            // Keep this renderable independently cache-safe. Consumers also
            // receive the metadata through $cacheability, but must not have to
            // remember to bubble it for the canonical picture.
            (new CacheableMetadata())
              ->addCacheableDependency($account)
              ->addCacheableDependency($file)
              ->applyTo($avatar);
          }
          return $avatar;
        }
      }
    }

    if ($this->avatarPool === NULL) {
      return NULL;
    }
    $entry = $this->avatarPool->fallbackEntry((int) $account?->id());
    if ($entry === NULL) {
      return NULL;
    }
    $avatar = [
      '#type' => 'html_tag',
      '#tag' => 'img',
      '#attributes' => $attributes + [
        'src' => $this->avatarPool->previewUrl($entry),
        'alt' => (string) new TranslatableMarkup('Avatar ilustrativo para @name', ['@name' => $name]),
        'loading' => 'lazy',
      ],
      '#cache' => ['contexts' => ['languages:language_interface']],
    ];
    if ($account !== NULL) {
      CacheableMetadata::createFromObject($account)->applyTo($avatar);
    }
    return $avatar;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Página pública do perfil em /u/<apelido>.
 *
 * Perfil inexistente, sem apelido ou fora do propósito MAIN responde 404. Perfil privado mostra só foto e apelido. Só entra no render o que PublicProfilePolicy liberar.
 */
final class PublicProfileController extends ControllerBase {

  /**
   * Propósito em que a página pública é servida.
   */
  private const PURPOSE = 'main';

  public function __construct(
    private readonly DomainPurposeManager $purposeManager,
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly PublicProfilePolicy $policy,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.domain_purpose'),
      $container->get('entity_type.manager'),
      $container->get('aculta_user.public_profile_policy'),
    );
  }

  /**
   * Renderiza o perfil público do apelido informado.
   */
  public function view(string $apelido): array {
    if ($this->purposeManager->getCurrentPurpose() !== self::PURPOSE) {
      throw new NotFoundHttpException();
    }

    $profile = $this->loadByNickname($apelido);
    if ($profile === NULL) {
      throw new NotFoundHttpException();
    }

    $account = $profile->getOwner();
    $cache = (new CacheableMetadata())
      ->addCacheTags(['profile:' . $profile->id(), 'user:' . $profile->getOwnerId()])
      ->addCacheContexts(['url.path']);

    // Perfil privado mostra só foto e apelido (PublicProfilePolicy). Sem apelido, a resposta é a de inexistente.
    $items = $this->policy->publicItems($this->valuesOf($profile, $account));
    if ($items === []) {
      throw new NotFoundHttpException();
    }

    $build = [
      '#cache' => ['tags' => $cache->getCacheTags(), 'contexts' => $cache->getCacheContexts()],
      'apelido' => ['#type' => 'html_tag', '#tag' => 'h1', '#value' => $apelido],
    ];

    if (isset($items['foto']) && $account !== NULL && $account->hasField('user_picture') && !$account->get('user_picture')->isEmpty()) {
      $build['foto'] = [
        '#theme' => 'image_style',
        '#style_name' => 'aculta_avatar',
        '#uri' => $account->get('user_picture')->entity->getFileUri(),
        '#alt' => $apelido,
      ];
    }
    if (isset($items['bio']) && $profile->hasField('field_bio') && !$profile->get('field_bio')->isEmpty()) {
      $build['bio'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => (string) $profile->get('field_bio')->value];
    }
    if (isset($items['email']) && $account !== NULL) {
      $build['email'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => (string) $account->getEmail()];
    }
    if (isset($items['redes']) && $profile->hasField('field_redes_sociais') && !$profile->get('field_redes_sociais')->isEmpty()) {
      $links = [];
      foreach ($profile->get('field_redes_sociais') as $item) {
        $links[] = ['#type' => 'link', '#title' => (string) $item->uri, '#url' => Url::fromUri((string) $item->uri), '#options' => ['attributes' => ['rel' => 'noopener nofollow']]];
      }
      $build['redes'] = $links;
    }

    return $build;
  }

  /**
   * Carrega o perfil participante cujo apelido é o informado.
   */
  private function loadByNickname(string $apelido): ?ProfileInterface {
    $storage = $this->entityTypeManagerService->getStorage('profile');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'participante')
      ->condition('field_nickname', $apelido)
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }
    $profile = $storage->load(reset($ids));
    return $profile instanceof ProfileInterface ? $profile : NULL;
  }

  /**
   * Lê os valores de visibilidade do perfil. Campo ausente usa o padrão: perfil público, itens privados.
   *
   * @return array{publico: bool, obrigatorio: bool, apelido: string, exibir_foto: bool, exibir_bio: bool, exibir_email: bool, exibir_redes: bool}
   */
  private function valuesOf(ProfileInterface $profile, $account): array {
    $flag = static function (string $field, bool $default) use ($profile): bool {
      if (!$profile->hasField($field) || $profile->get($field)->isEmpty()) {
        return $default;
      }
      return (bool) $profile->get($field)->value;
    };

    return [
      'publico' => $flag('field_perfil_publico', TRUE),
      'obrigatorio' => $account !== NULL && PublicProfilePolicy::isObrigatorioPublico($account->getRoles()),
      'apelido' => (string) $profile->get('field_nickname')->value,
      'exibir_foto' => $flag('field_exibir_foto', FALSE),
      'exibir_bio' => $flag('field_exibir_bio', FALSE),
      'exibir_email' => $flag('field_exibir_email', FALSE),
      'exibir_redes' => $flag('field_exibir_redes', FALSE),
    ];
  }

}

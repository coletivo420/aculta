<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Social;

use Drupal\profile\Entity\ProfileInterface;

/**
 * Provides the profile's social links after network validation. */
interface SocialNetworksInterface {

  /**
   * Public labels and hosts supported by the shared social-link policy.
   */
  public const NETWORKS = [
    'instagram' => ['label' => 'Instagram', 'type' => 'url', 'hosts' => ['instagram.com']],
    'facebook' => ['label' => 'Facebook', 'type' => 'url', 'hosts' => ['facebook.com', 'fb.com', 'fb.me']],
    'reddit' => ['label' => 'Reddit', 'type' => 'url', 'hosts' => ['reddit.com', 'redd.it']],
  ];

  /**
   * Returns at most one supported network URL per profile network.
   *
   * @return array<string, string>
   *   Network machine name to validated URL.
   */
  public function linksOf(ProfileInterface $profile): array;

}

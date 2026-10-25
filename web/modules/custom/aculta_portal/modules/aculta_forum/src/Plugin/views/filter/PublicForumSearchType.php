<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\views\filter;

use Drupal\search_api\Plugin\views\filter\SearchApiString;
use Drupal\views\Attribute\ViewsFilter;

/**
 * Restricts search results to the public Forum bundles.
 *
 * It keeps the Search API string filter behavior and adds a schema entry that
 * accepts the list of bundles used by the public search View.
 */
#[ViewsFilter('aculta_forum_search_type')]
final class PublicForumSearchType extends SearchApiString {

}

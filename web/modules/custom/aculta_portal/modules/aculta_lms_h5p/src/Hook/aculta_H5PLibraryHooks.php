<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Hook;

use Drupal\aculta_lms_h5p\AssetFileStorage;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PLibraryIdentifier;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Builds dynamic Drupal libraries for installed H5P content types. */
final class aculta_H5PLibraryHooks {

  public function __construct(
    #[Autowire(service: 'database')]
    private readonly Connection $database,
    #[Autowire(service: 'aculta_lms_h5p.framework')]
    private readonly aculta_H5PDrupal $framework,
    #[Autowire(service: 'aculta_lms_h5p.asset_file_manager')]
    private readonly AssetFileStorage $assetManager,
    #[Autowire(service: 'file_url_generator')]
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  #[Hook('library_info_build')]
  public function libraryInfoBuild(): array {
    $database = $this->database;
    $this->assetManager->mirrorAssets();
    $libraries = [];

    // Register jQuery version that H5P depends on.
    $libraries['h5p.jquery'] = [
      'version' => '1.9.1',
      'license' => [
        'name' => 'MIT',
        'url' => 'https://jquery.org/license/',
        'gpl-compatible' => TRUE,
      ],
    ];
    $h5p_jquery_js_key = $this->fileUrlGenerator->generate('assets://h5p/h5p-core/js/jquery.js', [
      'absolute' => TRUE,
      'language' => FALSE,
    ])->toString(TRUE)->getGeneratedUrl();
    $libraries['h5p.jquery']['js'][$h5p_jquery_js_key] = [
      'minified' => TRUE,
      'preprocess' => FALSE,
    ];

    // Register script for converting drupalSetting to H5PIntegration.
    $libraries['h5p.integration'] = [
      'version' => '2.0',
      'license' => [
        'name' => 'MIT',
        'url' => 'https://h5p.org',
        'gpl-compatible' => TRUE,
      ],
      'js' => [
        'js/h5p-integration.js' => [],
      ],
      'dependencies' => [
        'core/drupal',
      ],
    ];

    // Content libraries metadata.
    $libraries['h5p.content'] = [
      'version' => '1.0',
      'license' => [
        'name' => 'GPL 3.0',
        'url' => 'https://github.com/h5p/h5p-php-library/blob/master/LICENSE.txt',
        'gpl-compatible' => TRUE,
      ],
      'dependencies' => [
        'core/drupalSettings',
        'aculta_lms_h5p/h5p.integration',
        'aculta_lms_h5p/h5p.jquery',
      ],
    ];

    // Register core JS needed when viewing H5P content.
    foreach (\H5PCore::$scripts as $script) {
      if ($script === 'js/jquery.js') {
        continue;
      }
      $js = $this->fileUrlGenerator->generate("assets://h5p/h5p-core/{$script}", [
        'absolute' => TRUE,
        'language' => FALSE,
      ])->toString(TRUE)->getGeneratedUrl();
      $libraries['h5p.content']['js'][$js] = [];
    }

    // Register core CSS needed when viewing H5P content.
    foreach (\H5PCore::$styles as $style) {
      $css = $this->fileUrlGenerator->generate("assets://h5p/h5p-core/{$style}", [
        'absolute' => TRUE,
        'language' => FALSE,
      ])->toString(TRUE)->getGeneratedUrl();
      $libraries['h5p.content']['css']['base'][$css] = [];
    }

    // Register H5P content dependent on jQuery.
    $libraries['h5p.admin'] = [
      'version' => '1.0',
      'license' => [
        'name' => 'MIT',
        'url' => 'https://h5p.org',
        'gpl-compatible' => TRUE,
      ],
      'dependencies' => [
        'aculta_lms_h5p/h5p.integration',
        'aculta_lms_h5p/h5p.jquery',
      ],
    ];
    // Admin css.
    $h5p_admin_css_base = $this->fileUrlGenerator->generate('assets://h5p/h5p-core/styles/h5p.css', [
      'absolute' => TRUE,
      'language' => FALSE,
    ])->toString(TRUE)->getGeneratedUrl();
    $h5p_admin_css_admin_base = $this->fileUrlGenerator->generate('assets://h5p/h5p-core/styles/h5p-admin.css', [
      'absolute' => TRUE,
      'language' => FALSE,
    ])->toString(TRUE)->getGeneratedUrl();
    $libraries['h5p.admin']['css']['base'][$h5p_admin_css_base] = [];
    $libraries['h5p.admin']['css']['base'][$h5p_admin_css_admin_base] = [];
    // Admin js.
    $h5p_admin_js_utils_base = $this->fileUrlGenerator->generate('assets://h5p/h5p-core/js/h5p-utils.js', [
      'absolute' => TRUE,
      'language' => FALSE,
    ])->toString(TRUE)->getGeneratedUrl();
    $libraries['h5p.admin']['js']['js/h5p-admin-integration.js'] = [];
    $libraries['h5p.admin']['js'][$h5p_admin_js_utils_base] = [];

    // Register core JS needed when administrating H5P libraries.
    $libraries['h5p.admin.library.list'] = [
      'version' => '1.0',
      'license' => [
        'name' => 'MIT',
        'url' => 'https://h5p.org',
        'gpl-compatible' => TRUE,
      ],
      'dependencies' => [
        'aculta_lms_h5p/h5p.admin',
      ],
    ];
    $h5p_admin_library_list_js_key = $this->fileUrlGenerator->generate('assets://h5p/h5p-core/js/h5p-library-list.js', [
      'absolute' => TRUE,
      'language' => FALSE,
    ])->toString(TRUE)->getGeneratedUrl();
    $libraries['h5p.admin.library.list']['js'][$h5p_admin_library_list_js_key] = [];

    $libraries['h5p.admin.library.upgrade'] = [
      'version' => '1.0',
      'license' => [
        'name' => 'MIT',
        'gpl-compatible' => TRUE,
        'url' => 'https://h5p.org',
      ],
      'dependencies' => [
        'aculta_lms_h5p/h5p.admin',
      ],
    ];
    $h5p_admin_library_upgrade_version_js_key = $this->fileUrlGenerator->generate('assets://h5p/h5p-core/js/h5p-version.js', [
      'absolute' => TRUE,
      'language' => FALSE,
    ])->toString(TRUE)->getGeneratedUrl();
    $h5p_admin_library_upgrade_content_upgrade_js_key = $this->fileUrlGenerator->generate('assets://h5p/h5p-core/js/h5p-content-upgrade.js', [
      'absolute' => TRUE,
      'language' => FALSE,
    ])->toString(TRUE)->getGeneratedUrl();
    $libraries['h5p.admin.library.upgrade']['js'][$h5p_admin_library_upgrade_version_js_key] = [];
    $libraries['h5p.admin.library.upgrade']['js'][$h5p_admin_library_upgrade_content_upgrade_js_key] = [];

    $libraries['h5p.admin.library.details'] = [
      'dependencies' => [
        'aculta_lms_h5p/h5p.admin',
      ],
    ];
    $h5p_admin_library_details_js_key = $this->fileUrlGenerator->generate('assets://h5p/h5p-core/js/h5p-library-details.js', [
      'absolute' => TRUE,
      'language' => FALSE,
    ])->toString(TRUE)->getGeneratedUrl();
    $libraries['h5p.admin.library.details']['js'][$h5p_admin_library_details_js_key] = [];

    // The PHP H5P Editor library supplies editor behavior; this Drupal library
    // attaches its ordered assets to the Group field widget.
    $libraries['h5p.editor'] = [
      // Leave the version unset so Drupal hashes asset contents for aggregate
      // cache keys and uses its resettable asset query string for standalone
      // files. A fixed version leaves browsers serving stale editor behavior.
      'license' => [
        'name' => 'GPL-3.0-only',
        'url' => 'https://github.com/h5p/h5p-editor-php-library',
        'gpl-compatible' => TRUE,
      ],
      'dependencies' => [
        'aculta_lms_h5p/h5p.content',
        'core/drupal',
        'core/drupalSettings',
        'core/jquery',
        'core/once',
      ],
      'js' => [],
      'css' => ['base' => []],
    ];
    foreach (\H5PEditor::$scripts as $script) {
      $url = $this->fileUrlGenerator->generate("assets://h5p/h5p-editor/{$script}", [
        'absolute' => TRUE,
        'language' => FALSE,
      ])->toString(TRUE)->getGeneratedUrl();
      $libraries['h5p.editor']['js'][$url] = ['preprocess' => FALSE];
    }
    // Dynamic library paths are relative to this module; including the module
    // path here makes Drupal prepend it twice and prevents editor startup.
    $application_script = 'js/h5peditor-application.js';
    $libraries['h5p.editor']['js'][$application_script] = ['preprocess' => FALSE];
    foreach (\H5PEditor::$styles as $style) {
      $url = $this->fileUrlGenerator->generate("assets://h5p/h5p-editor/{$style}", [
        'absolute' => TRUE,
        'language' => FALSE,
      ])->toString(TRUE)->getGeneratedUrl();
      $libraries['h5p.editor']['css']['base'][$url] = [];
    }

    // Load installed libraries
    $h5p_libraries = $database->query("
      SELECT
        library_id AS id,
        machine_name AS name,
        major_version AS major,
        minor_version AS minor,
        preloaded_js AS js_csv,
        preloaded_css AS css_csv,
        drop_library_css
      FROM {h5p_libraries}");

    $libraries_prefix = '/' . aculta_H5PDrupal::getRelativeaculta_H5PPath() . '/libraries';
    foreach ($h5p_libraries as $h5p_library) {

      $js = [];
      $scripts = explode(',', $h5p_library->js_csv);
      foreach ($scripts as &$script) {
        $script = trim($script);
        if ($script) {
          // These files are private Drupal-managed assets served by the
          // library route, not files beneath DRUPAL_ROOT for aggregation.
          $js["$libraries_prefix/{$h5p_library->name}-{$h5p_library->major}.{$h5p_library->minor}/$script"] = [
            'preprocess' => FALSE,
          ];
        }
      }

      $css = [];
      $styles = explode(',', $h5p_library->css_csv);
      foreach ($styles as &$style) {
        $style = trim($style);
        if ($style) {
          $css["$libraries_prefix/{$h5p_library->name}-{$h5p_library->major}.{$h5p_library->minor}/$style"] = [
            'preprocess' => FALSE,
          ];
        }
      }

      // Load dependencies
      $h5p_dependencies = $database->query(
          "SELECT hl.machine_name AS name,
                  hl.major_version AS major,
                  hl.minor_version AS minor
             FROM {h5p_libraries_libraries} hll
             JOIN {h5p_libraries} hl ON hll.required_library_id = hl.library_id
            WHERE hll.library_id = :library_id
              AND dependency_type = 'preloaded'",
        [':library_id' => $h5p_library->id]);

      $dependencies = ['aculta_lms_h5p/h5p.content'];
      foreach ($h5p_dependencies as $h5p_dependency) {
        $dependencies[] = 'aculta_lms_h5p/' . aculta_H5PLibraryIdentifier::toDrupalLibraryId($h5p_dependency);
      }

      $libraries[aculta_H5PLibraryIdentifier::toDrupalLibraryId($h5p_library)] = [
        'js' => $js,
        'css' => [
          'base' => $css,
        ],
        'dependencies' => $dependencies,
      ];
    }

    return $libraries;
  }

}

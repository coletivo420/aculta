<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Controller;

use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PEvent;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The H5PContentUpgrade controller
 */
class H5PContentUpgrade extends ControllerBase {

  protected $database;

  /**
   * constructor.
   */
  public function __construct(Connection $database, private readonly RequestStack $requestStack) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    $controller = new static($container->get('database'), $container->get('request_stack'));
    return $controller;
  }

  /**
   * Helper for getting library versions
   *
   * @param string $library_id
   *
   * @return array
   */
  public static function getLibraryVersions(Connection $database, $library_id) {
    $query = $database->select('h5p_libraries', 'hl1');
    $query->join('h5p_libraries', 'hl2', 'hl1.machine_name = hl2.machine_name');
    $query->condition('hl1.library_id', $library_id, '=');
    $query->addField('hl2', 'library_id', 'id');
    $query->fields('hl2', ['machine_name', 'title', 'major_version', 'minor_version', 'patch_version']);
    $query->orderBy('hl2.title', 'ASC');
    $query->orderBy('hl2.major_version', 'ASC');
    $query->orderBy('hl2.minor_version', 'ASC');
    $results = $query->execute();

    $versions = [];
    foreach ($results as $result) {
      $versions[$result->id] = $result;
    }

    return $versions;
  }

  /**
   * Creates the title for the upgrade content page
   *
   * @param string $library_id
   *
   * @return string
   */
  public function pageTitle($library_id) {
    $query = $this->database->select('h5p_libraries', 'l');
    $query->condition('library_id', $library_id, '=');
    $query->fields('l', ['title', 'major_version', 'minor_version', 'patch_version']);
    $library = $query->execute()->fetch();
    if (!$library) {
      return $this->t('Biblioteca não encontrada.');
    }

    return $this->t('Atualizar atividades da biblioteca @library', ['@library' => $library->title . ' (' . \H5PCore::libraryVersion($library) . ')']);
  }

  /**
   * Handles saving of upgraded content. Returns new batch
   *
   * @param string $library_id
   *
   * @return JsonResponse|array
   */
  public function upgrade($library_id) {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL || $request->getMethod() !== 'POST') {
      return new JsonResponse(['error' => (string) $this->t('Método não permitido.')], 405);
    }
    // Verify security token
    if (!\H5PCore::validToken('contentupgrade', $request->request->getString('token'))) {
      return new JsonResponse(['error' => (string) $this->t('Token de segurança inválido.')], 403);
    }

    // Get the library we're upgrading to
    $targetLibraryId = $request->request->getInt('libraryId');
    if (!ctype_digit((string) $library_id) || $targetLibraryId < 1) {
      return new JsonResponse(['error' => (string) $this->t('Biblioteca de destino inválida.')], 400);
    }
    $to_library = $this->database->query('SELECT library_id AS id, machine_name AS name, major_version, minor_version FROM {h5p_libraries} WHERE library_id = :id', [':id' => $targetLibraryId])->fetch();
    if (!$to_library) {
      return new JsonResponse(['error' => (string) $this->t('Biblioteca não encontrada.')], 404);
    }

    // Prepare response
    $out = [
      'params' => [],
      'token' => \H5PCore::createToken('contentupgrade'),
    ];

    // Prepare our interface
    $interface = H5PDrupal::getInstance('interface');

    // Get updated params
    $params = $request->request->get('params');
    if (is_string($params) && $params !== '' && strlen($params) <= 2_000_000) {
      // Update params.
      $params = json_decode($params, TRUE, 32);
      if (!is_array($params)) {
        return new JsonResponse(['error' => (string) $this->t('Os parâmetros enviados estão inválidos.')], 400);
      }
      foreach ($params as $id => $param) {
        if (!ctype_digit((string) $id) || !is_string($param)) {
          continue;
        }
        $upgraded = json_decode($param);
        if (!is_object($upgraded) || !isset($upgraded->params)) {
          continue;
        }
        $metadata = isset($upgraded->metadata) && (is_object($upgraded->metadata) || is_array($upgraded->metadata)) ? $upgraded->metadata : array();

        $fields = array_merge(\H5PMetadata::toDBArray($metadata, false, false), array(
          'library_id' => $to_library->id,
          'parameters' => json_encode($upgraded->params),
          'filtered_parameters' => '',
        ));

        $this->database->update('h5p_content')
          ->fields($fields)
          ->condition('id', $id)
          ->condition('library_id', $library_id)
          ->execute();

        // Log content upgrade successful
        new H5PEvent('content', 'upgrade',
          $id,
          (is_object($metadata) ? ($metadata->title ?? '') : ($metadata['title'] ?? '')),
          $to_library->name,
          $to_library->major_version . '.' . $to_library->minor_version);

        // Clear content cache
        $interface->updateContentFields($id, ['filtered' => '']);
      }
    }

    // Determine if any content has been skipped during the process
    $skipped = $request->request->get('skipped');
    if (is_string($skipped) && $skipped !== '' && strlen($skipped) <= 65536) {
      $decodedSkipped = json_decode($skipped, TRUE);
      $out['skipped'] = is_array($decodedSkipped) ? $decodedSkipped : [];
      // Clean up input, only numbers
      $out['skipped'] = array_values(array_unique(array_filter(array_map(
        static fn ($id): int => filter_var($id, FILTER_VALIDATE_INT) ?: 0,
        $out['skipped'],
      ), static fn (int $id): bool => $id > 0)));
      $skipped = implode(',', $out['skipped']);
    }
    else {
      $out['skipped'] = array();
    }

    // Get number of contents for this library
    $out['left'] = $interface->getNumContent($library_id, $skipped);

    if ($out['left']) {
      $skip_query = empty($skipped) ? '' : " AND id NOT IN ($skipped)";

      // Find the 40 first contents using library and add to params
      $contents = $this->database->query(
        "SELECT id, parameters AS params, title, authors, source, license,
                license_version, license_extras, year_from, year_to, changes,
                author_comments, default_language, a11y_title
           FROM {h5p_content}
          WHERE library_id = :id
                {$skip_query}
          LIMIT 40", [
        ':id' => $library_id
      ]);

      foreach ($contents as $content) {
        $out['params'][$content->id] =
          '{"params":' . $content->params .
          ',"metadata":' . \H5PMetadata::toJSON($content) . '}';
      }
    }

    return new JsonResponse($out);
  }

  /**
   * AJAX loading of libraries for content upgrade script.
   *
   * @param string $name Machine name
   * @param int $major
   * @param int $minor
   *
   * @return JsonResponse
   */
  public function prepareUpgrade($name, $major, $minor) {
    $library = (object) [
      'name' => $name,
      'version' => (object) [
        'major' => $major,
        'minor' => $minor,
      ],
    ];

    $core = H5PDrupal::getInstance('core');
    $library->semantics = $core->loadLibrarySemantics($library->name, $library->version->major, $library->version->minor);

    $upgrades_script = H5PDrupal::getRelativeH5PPath() . "/libraries/{$library->name}-{$library->version->major}.{$library->version->minor}/upgrades.js";

    if (file_exists($upgrades_script)) {
      $library->upgradesScript = base_path() . $upgrades_script;
    }

    return new JsonResponse($library);
  }
}

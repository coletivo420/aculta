<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Controller;

use Drupal\aculta_lms_h5p\Access\H5PCourseAccessManager;
use Drupal\aculta_lms_h5p\Access\H5PLibraryAccessManager;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;
use Drupal\aculta_lms_h5p\H5PEditor\H5PEditorUtilities;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Serves only read and validation requests for an editor scoped to one course. */
final class H5PEditorAjaxController implements ContainerInjectionInterface {

  public function __construct(
    private readonly H5PCourseAccessManager $courseAccess,
    private readonly H5PLibraryAccessManager $libraryAccess,
    private readonly AccountProxyInterface $currentUser,
    private readonly LanguageManagerInterface $languageManager,
    private readonly RequestStack $requestStack,
    private readonly TranslationInterface $translation,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_lms_h5p.course_access'),
      $container->get('aculta_lms_h5p.library_access'),
      $container->get('current_user'),
      $container->get('language_manager'),
      $container->get('request_stack'),
      $container->get('string_translation'),
    );
  }

  /** Dispatches the editor's non-mutating requests after course authorization. */
  public function handle(int $group_id, string $token, int $content_id, string $action, string $arguments = ''): JsonResponse {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL
      || !$this->courseAccess->editorAccess($group_id, $content_id, $this->currentUser)->isAllowed()
      || !\H5PCore::validToken('editorajax', $token)) {
      throw new AccessDeniedHttpException();
    }

    $response = match ($action) {
      'libraries' => $this->libraries($request->getMethod()),
      'single-library' => $this->singleLibrary($request->getMethod(), $arguments),
      'translations' => $this->translations($request->getMethod(), $request->query->getString('language')),
      'filter' => $this->filter($request->getMethod()),
      'files', 'library-upload', 'library-install', 'get-hub-content', 'content-type-cache' => $this->unavailable($request->getMethod()),
      default => throw new NotFoundHttpException(),
    };
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

  /** Lists installed local libraries. The H5P API returns this list as a flat array. */
  private function libraries(string $method): JsonResponse {
    if ($method !== 'GET') {
      throw new NotFoundHttpException();
    }
    $libraries = H5PEditorUtilities::getInstance()->getLibraries();
    $libraries = array_values(array_filter($libraries, fn (object $library): bool => $this->libraryAccess->canUse(
      (string) $library->name,
      (int) $library->majorVersion,
      (int) $library->minorVersion,
      $this->currentUser,
    )));
    return new JsonResponse($libraries);
  }

  /** Returns metadata and assets for one installed, permitted library. */
  private function singleLibrary(string $method, string $arguments): JsonResponse {
    if ($method !== 'GET' || !preg_match('@^([A-Za-z][A-Za-z0-9_.-]*)/(\d{1,4})/(\d{1,4})$@D', $arguments, $matches)) {
      throw new NotFoundHttpException();
    }
    [$machine, $major, $minor] = [$matches[1], (int) $matches[2], (int) $matches[3]];
    if (!$this->libraryAccess->canUse($machine, $major, $minor, $this->currentUser)) {
      throw new NotFoundHttpException();
    }

    $language = $this->languageManager->getCurrentLanguage()->getId();
    $data = H5PEditorUtilities::getInstance()->getLibraryData(
      $machine,
      $major,
      $minor,
      $language,
      'libraries/',
      '',
      '',
    );
    return new JsonResponse($data);
  }

  /** Returns only requested translations for installed local libraries. */
  private function translations(string $method, string $language): JsonResponse {
    if ($method !== 'POST' || !preg_match('/^[a-zA-Z0-9_-]{2,12}$/D', $language)) {
      throw new NotFoundHttpException();
    }
    $request = $this->requestStack->getCurrentRequest();
    $libraries = $request?->request->all('libraries') ?? [];
    if (count($libraries) > 100) {
      throw new NotFoundHttpException();
    }

    $valid = [];
    foreach ($libraries as $name) {
      if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_.-]* \d{1,4}\.\d{1,4}$/D', $name)) {
        throw new NotFoundHttpException();
      }
      $valid[] = $name;
    }
    $data = H5PEditorUtilities::getInstance()->ajaxInterface->getTranslations($valid, $language);
    return new JsonResponse(['success' => TRUE, 'data' => (object) $data]);
  }

  /** Applies the installed library's H5P semantics filter without saving content. */
  private function filter(string $method): JsonResponse {
    if ($method !== 'POST') {
      throw new NotFoundHttpException();
    }
    $request = $this->requestStack->getCurrentRequest();
    $raw = $request?->request->getString('libraryParameters') ?? '';
    if ($raw === '' || strlen($raw) > 2_000_000) {
      throw new NotFoundHttpException();
    }
    $parameters = json_decode($raw);
    if (!$parameters instanceof \stdClass || !isset($parameters->library) || !is_string($parameters->library)) {
      throw new NotFoundHttpException();
    }
    $parsed = \H5PCore::libraryFromString($parameters->library);
    if (!is_array($parsed)) {
      throw new NotFoundHttpException();
    }
    if (!$this->libraryAccess->canUse(
      (string) $parsed['machineName'],
      (int) $parsed['majorVersion'],
      (int) $parsed['minorVersion'],
      $this->currentUser,
    )) {
      throw new NotFoundHttpException();
    }

    $validator = H5PDrupal::getInstance('contentvalidator');
    $validator->validateLibrary($parameters, (object) ['options' => [$parameters->library]]);
    return new JsonResponse(['success' => TRUE, 'data' => $parameters]);
  }

  /** Explicit JSON failure for upload and remote library actions still closed. */
  private function unavailable(string $method): JsonResponse {
    if ($method !== 'POST') {
      throw new NotFoundHttpException();
    }
    return new JsonResponse([
      'success' => FALSE,
      'errorCode' => 'H5P_UPLOAD_UNAVAILABLE',
      'message' => $this->translation->translate('Envio de arquivos e instalação de bibliotecas ainda não estão disponíveis.'),
    ], 403);
  }

}

<?php

// Fixtures de teste das regras locais em rules/aculta-php.yml.
// Linhas marcadas com "ruleid:" devem gerar achado; "ok:" não podem gerar.
// Não contém segredos nem dados reais.

// ruleid: aculta-php-eval-or-dynamic-exec
eval($codigo);
// ruleid: aculta-php-eval-or-dynamic-exec
shell_exec('ls');
// ruleid: aculta-php-eval-or-dynamic-exec
passthru($cmd, $code);
// ok: aculta-php-eval-or-dynamic-exec
$resultado = json_decode($texto, TRUE);

// ruleid: aculta-php-unserialize-untrusted
$dados = unserialize($payload);
// ok: aculta-php-unserialize-untrusted
$dados = json_decode($payload, TRUE);

// ruleid: aculta-php-superglobal-input
$id = $_GET['id'];
// ruleid: aculta-php-superglobal-input
$body = $_POST['campo'];
// ok: aculta-php-superglobal-input
$id = $request->query->get('id');

// ruleid: aculta-php-raw-sql-concat
$connection->query("SELECT * FROM users WHERE id = " . $id);
// ok: aculta-php-raw-sql-concat
$connection->query("SELECT * FROM users WHERE id = :id", [':id' => $id]);

class StaticServiceFixture {
  public function unsafe(): void {
    // ruleid: aculta-php-static-drupal-service
    $db = \Drupal::database();
  }
}

trait StaticServiceTraitFixture {
  public function unsafeTrait(): void {
    // ruleid: aculta-php-static-drupal-service
    $renderer = \Drupal::service('renderer');
  }
}

// Acesso procedural de scripts/hooks não oferece injeção por construtor.
// ok: aculta-php-static-drupal-service
$db = \Drupal::database();
// O singleton próprio do fork H5P não é a fachada global do Drupal.
// ok: aculta-php-static-drupal-service
$manager = aculta_H5PDrupal::getInstance();
// ok: aculta-php-static-drupal-service
$db = $this->database;

// ruleid: aculta-php-raw-redirect-cross-host
return new \Symfony\Component\HttpFoundation\RedirectResponse($url);
// ok: aculta-php-raw-redirect-cross-host
return new \Drupal\Core\Routing\TrustedRedirectResponse($url);

// ruleid: aculta-php-markup-raw-output
$build = ['#markup' => $entrada];
// ok: aculta-php-markup-raw-output
$build = ['#markup' => $this->t('Texto fixo')];

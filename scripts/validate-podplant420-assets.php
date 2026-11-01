<?php

declare(strict_types=1);

$root = $argv[1] ?? dirname(__DIR__);
$base = rtrim($root, DIRECTORY_SEPARATOR) . '/web/themes/custom/aculta420/assets/branding/podplant420/web';
$expected = [
  'stacked/podplant420-stacked-on-dark-128w.webp' => [128, 128],
  'stacked/podplant420-stacked-on-dark-256w.webp' => [256, 256],
  'stacked/podplant420-stacked-on-dark-512w.webp' => [512, 512],
  'stacked/podplant420-stacked-on-light-128w.webp' => [128, 128],
  'stacked/podplant420-stacked-on-light-256w.webp' => [256, 256],
  'stacked/podplant420-stacked-on-light-512w.webp' => [512, 512],
  'horizontal/podplant420-horizontal-on-dark-240w.webp' => [240, 99],
  'horizontal/podplant420-horizontal-on-dark-480w.webp' => [480, 198],
  'horizontal/podplant420-horizontal-on-light-240w.webp' => [240, 99],
  'horizontal/podplant420-horizontal-on-light-480w.webp' => [480, 199],
];

$errors = [];
$hashes = [];

foreach ($expected as $relative => [$width, $height]) {
  $path = $base . '/' . $relative;

  if (!is_file($path) || !is_readable($path)) {
    $errors[] = "missing/unreadable: {$relative}";
    continue;
  }

  if (filesize($path) <= 0 || filesize($path) > 32768) {
    $errors[] = "unexpected size: {$relative}";
  }

  $info = @getimagesize($path);
  if (!$info || $info[0] !== $width || $info[1] !== $height || ($info['mime'] ?? '') !== 'image/webp') {
    $errors[] = "invalid dimensions/mime: {$relative}";
  }

  $bytes = file_get_contents($path);
  if ($bytes === false || substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WEBP' || strpos($bytes, 'ALPH') === false) {
    $errors[] = "invalid WebP transparency container: {$relative}";
  }

  if (!preg_match('/^podplant420-(stacked|horizontal)-on-(dark|light)-(128|240|256|480|512)w\\.webp$/', basename($path))) {
    $errors[] = "invalid name: {$relative}";
  }

  $hash = hash_file('sha256', $path);
  if (isset($hashes[$hash])) {
    $errors[] = "duplicate payload: {$relative} == {$hashes[$hash]}";
  }
  $hashes[$hash] = $relative;
}

if ($errors) {
  fwrite(STDERR, "Podplant420 assets: FAIL\n- " . implode("\n- ", $errors) . "\n");
  exit(1);
}

printf("Podplant420 assets: PASS (%d files)\n", count($expected));

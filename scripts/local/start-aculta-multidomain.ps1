$ErrorActionPreference = 'Stop'
$listenAddress = '127.0.0.1:8080'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..\web')).Path
$router = Join-Path $root '.ht.router.php'
$php = (Get-Command php -ErrorAction Stop).Source

$null = Set-Location -LiteralPath $root

$listener = Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue
if ($listener) {
  $owners = ($listener | Select-Object -ExpandProperty OwningProcess -Unique) -join ', '
  throw "A porta 8080 já está em uso (PID: $owners). O script não encerra processos existentes."
}
if (-not (Test-Path -LiteralPath $router -PathType Leaf)) {
  throw 'O router oficial do PHP/Drupal web/.ht.router.php não foi encontrado.'
}

@(
  'http://aculta.test:8080'
  'http://conta.aculta.test:8080'
  'http://apoio.aculta.test:8080'
  'http://revista420.aculta.test:8080'
  'http://wiki420.aculta.test:8080'
  'http://loja.aculta.test:8080'
  'http://cursos.aculta.test:8080'
) | Write-Output

& $php -S $listenAddress -t $root $router

param(
  [switch]$Remove
)

$ErrorActionPreference = 'Stop'
$hostsPath = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'
$beginMarker = '# BEGIN ACULTA LOCAL DOMAINS'
$endMarker = '# END ACULTA LOCAL DOMAINS'
$entries = @(
  '127.0.0.1 aculta.test'
  '127.0.0.1 conta.aculta.test'
  '127.0.0.1 apoio.aculta.test'
  '127.0.0.1 revista420.aculta.test'
  '127.0.0.1 wiki420.aculta.test'
  '127.0.0.1 loja.aculta.test'
  '127.0.0.1 cursos.aculta.test'
)

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = [Security.Principal.WindowsPrincipal]::new($identity)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
  throw "Execute este script em PowerShell como Administrador: .\scripts\local\configure-aculta-hosts.ps1$(if ($Remove) { ' -Remove' })"
}
if (-not (Test-Path -LiteralPath $hostsPath -PathType Leaf)) {
  throw "Arquivo hosts não encontrado no caminho esperado do Windows."
}

$content = [IO.File]::ReadAllText($hostsPath)
$start = $content.IndexOf($beginMarker, [StringComparison]::Ordinal)
$finish = $content.IndexOf($endMarker, [StringComparison]::Ordinal)
if (($start -ge 0) -xor ($finish -ge 0)) {
  throw 'Marcadores ACULTA incompletos no arquivo hosts; nenhuma alteração foi feita.'
}
if ($start -ge 0 -and $finish -lt $start) {
  throw 'Ordem inválida dos marcadores ACULTA no arquivo hosts; nenhuma alteração foi feita.'
}

$timestamp = Get-Date -Format 'yyyy-MM-dd_HHmmss'
$backupDirectory = Join-Path $env:USERPROFILE 'aculta-backups'
New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null
$backupPath = Join-Path $backupDirectory "hosts-pre-aculta-$timestamp.bak"
Copy-Item -LiteralPath $hostsPath -Destination $backupPath

if ($start -ge 0) {
  $afterMarker = $finish + $endMarker.Length
  $content = $content.Remove($start, $afterMarker - $start)
}

if (-not $Remove) {
  $managedHosts = @('aculta.test','conta.aculta.test','apoio.aculta.test','revista420.aculta.test','wiki420.aculta.test','loja.aculta.test','cursos.aculta.test')
  $lines = $content -split "`r?`n"
  $content = (($lines | Where-Object {
    $tokens = $_.Trim() -split '\s+'
    -not ($tokens | Where-Object { $managedHosts -contains $_ })
  }) -join [Environment]::NewLine)
  $block = $beginMarker + [Environment]::NewLine + ($entries -join [Environment]::NewLine) + [Environment]::NewLine + $endMarker
  $content = $content.TrimEnd("`r", "`n") + [Environment]::NewLine + $block + [Environment]::NewLine
}

[IO.File]::WriteAllText($hostsPath, $content, [Text.UTF8Encoding]::new($false))
& ipconfig.exe /flushdns | Out-Null
if ($LASTEXITCODE -ne 0) {
  throw 'Não foi possível limpar o cache DNS do Windows.'
}

if (-not $Remove) {
  foreach ($domain in @('aculta.test','conta.aculta.test','apoio.aculta.test','revista420.aculta.test','wiki420.aculta.test','loja.aculta.test','cursos.aculta.test')) {
    $addresses = [Net.Dns]::GetHostAddresses($domain) | ForEach-Object { $_.IPAddressToString }
    if ($addresses -notcontains '127.0.0.1') {
      throw "A validação DNS falhou para $domain. Backup: $backupPath"
    }
  }
}

Write-Output "Hosts ACULTA atualizados. Backup: $backupPath"

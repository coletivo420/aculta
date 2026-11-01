<#
.SYNOPSIS
Inicia o servidor local com o document root e o router do Drupal.
.EXAMPLE
.\scripts\serve.ps1
.EXAMPLE
.\scripts\serve.ps1 -PhpExecutable 'C:\caminho\php.exe' -Port 8080
#>
[CmdletBinding()]
param (
  [ValidateRange(1024, 65535)]
  [int]$Port = 8080,
  [string]$PhpExecutable = 'php'
)

$ErrorActionPreference = 'Stop'
$phpCommand = Get-Command -Name $PhpExecutable -CommandType Application -ErrorAction Stop
$projectRoot = Split-Path -Parent $PSScriptRoot
$webRoot = Join-Path -Path $projectRoot -ChildPath 'web'

Push-Location -LiteralPath $webRoot
try {
  # Drupal gera novos agregados CSS/JS sob demanda por meio deste router.
  & $phpCommand.Source -S "localhost:$Port" '.ht.router.php'
}
finally {
  Pop-Location
}

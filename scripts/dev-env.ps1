<#
.SYNOPSIS
  Prepares the CURRENT PowerShell session for working on Athar (WAMP + Node).

.DESCRIPTION
  Your Windows PATH points at C:\xampp\php, which does not exist, so `php` is not found.
  This script fixes that for this terminal window only. It changes nothing permanently.

  Usage (dot-source it so the PATH change stays in your session):
      . .\scripts\dev-env.ps1
#>

$phpDir = Get-ChildItem 'C:\wamp64\bin\php' -Directory -Filter 'php8.3*' -ErrorAction SilentlyContinue |
    Sort-Object Name -Descending | Select-Object -First 1

if (-not $phpDir) {
    Write-Warning 'PHP 8.3 not found under C:\wamp64\bin\php. Laravel 13 needs PHP 8.3 or newer.'
    return
}

$extra = @($phpDir.FullName, 'C:\composer', 'C:\Program Files\nodejs')
$machine = [Environment]::GetEnvironmentVariable('Path', 'Machine')
$user = [Environment]::GetEnvironmentVariable('Path', 'User')
$env:Path = (($extra + @($machine, $user)) -join ';')

Write-Host ("php      : " + (php -r 'echo PHP_VERSION;'))
Write-Host ("composer : " + ((composer --version 2>$null) -replace 'Composer version ', '' -split ' ')[0])
Write-Host ("node     : " + (node -v 2>$null))

$mysqld = Get-Process mysqld -ErrorAction SilentlyContinue
if ($mysqld) {
    Write-Host 'mysql    : running'
} else {
    Write-Warning 'MySQL is not running. Start WAMP (green icon), or see README "Menjalankan MySQL".'
}

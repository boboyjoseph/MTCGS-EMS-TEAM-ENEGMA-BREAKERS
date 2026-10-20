param(
    [string] $BackupRoot = (Join-Path $env:LOCALAPPDATA 'MTCGS-EMS-Backups')
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = Split-Path -Parent $PSScriptRoot
$BackupRoot = [System.IO.Path]::GetFullPath($BackupRoot)
$repositoryRoot = [System.IO.Path]::GetFullPath($repositoryRoot)

if ($BackupRoot.StartsWith($repositoryRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Store database backups outside the Git repository.'
}

$driveLetter = [System.IO.Path]::GetPathRoot($BackupRoot).Substring(0, 1)
$volume = Get-Volume -DriveLetter $driveLetter
if ($volume.FileSystem -ne 'NTFS') {
    throw "Refusing to write a plaintext database backup to $($volume.FileSystem). Choose an NTFS folder with access controls."
}

if (-not (Test-Path -LiteralPath $BackupRoot -PathType Container)) {
    New-Item -ItemType Directory -Path $BackupRoot -Force | Out-Null
}

$runName = 'MTCGS-EMS-' + (Get-Date -Format 'yyyyMMdd-HHmmss')
$runDirectory = Join-Path $BackupRoot $runName
New-Item -ItemType Directory -Path $runDirectory -ErrorAction Stop | Out-Null

$identity = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
& icacls.exe $runDirectory /inheritance:r /grant:r "$($identity):(OI)(CI)F" | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Could not restrict the new backup directory to the current Windows account.'
}
$acl = (& icacls.exe $runDirectory | Out-String)
if ($LASTEXITCODE -ne 0 -or $acl -notmatch [regex]::Escape($identity) -or $acl -match '(?i)Everyone|BUILTIN\\Users') {
    throw 'The backup directory ACL is not private to the current Windows account.'
}

$phpScript = Join-Path $PSScriptRoot 'database-safety-check.php'
$previousLocation = Get-Location
try {
    Set-Location -LiteralPath $repositoryRoot
    & php $phpScript $runDirectory
    if ($LASTEXITCODE -ne 0) {
        throw "Database safety check failed with exit code $LASTEXITCODE. The source database was not intentionally modified; inspect the isolated restore-test database before retrying."
    }
}
finally {
    Set-Location -LiteralPath $previousLocation
}

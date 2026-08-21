# Builds the CHIM server tarball and the Skyrim MO2/Nexus zip.
[CmdletBinding()]
param(
    [string]$GitHubRepo,
    [string]$PexPath,
    [string]$Mo2PatchPath
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
Add-Type -AssemblyName System.IO.Compression.FileSystem

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$pluginRoot = $repoRoot
$skyrimPatchRoot = Join-Path $repoRoot 'SkyrimPatch'
$releaseRoot = Join-Path $repoRoot 'release'
$utf8 = New-Object System.Text.UTF8Encoding $false

if ([string]::IsNullOrWhiteSpace($PexPath)) {
    $PexPath = Join-Path $skyrimPatchRoot 'Scripts'
}

$manifest = Get-Content -LiteralPath (Join-Path $pluginRoot 'manifest.json') -Raw -Encoding UTF8 | ConvertFrom-Json
$pluginName = [string]$manifest.name
$version = [string]$manifest.version
$scriptNames = @('_SNNeedsAPI', '_SNChimBridge', '_snquestscript', '_snautomation')

if (-not [string]::IsNullOrWhiteSpace($GitHubRepo)) {
    $manifest | Add-Member -NotePropertyName git_repo -NotePropertyValue $GitHubRepo -Force
    $downloadBase = "https://github.com/$GitHubRepo/releases/latest/download"
    if (-not $manifest.channels.main.PSObject.Properties['package_urls']) {
        $manifest.channels.main | Add-Member -NotePropertyName package_urls -NotePropertyValue @() -Force
    }
    $manifest.channels.main.package_urls = @(
        "$downloadBase/$pluginName.tar.gz",
        "$downloadBase/$pluginName.tar"
    )
    $manifest | Add-Member -NotePropertyName mod_download_url -NotePropertyValue "$downloadBase/iNeed-CHIM-Patch.zip" -Force
    $json = ($manifest | ConvertTo-Json -Depth 20).Trim() + "`n"
    [System.IO.File]::WriteAllText((Join-Path $pluginRoot 'manifest.json'), $json, $utf8)
}

function Copy-PluginFiles {
    param([string]$Destination)

    [System.IO.Directory]::CreateDirectory($Destination) | Out-Null
    foreach ($file in @('context_pre.php', 'globals.php', 'index.php', 'manifest.json', 'README.md', 'dwemer-package.json')) {
        $source = Join-Path $pluginRoot $file
        if (Test-Path -LiteralPath $source) {
            Copy-Item -LiteralPath $source -Destination (Join-Path $Destination $file)
        }
    }
    foreach ($directory in @('lib', 'migrations')) {
        $source = Join-Path $pluginRoot $directory
        if (Test-Path -LiteralPath $source) {
            Copy-Item -LiteralPath $source -Destination $Destination -Recurse
        }
    }
}

function New-ZipFromDirectory {
    param(
        [string]$SourceDirectory,
        [string]$ZipPath,
        [bool]$IncludeRoot
    )

    if (Test-Path -LiteralPath $ZipPath) {
        Remove-Item -LiteralPath $ZipPath -Force
    }
    [System.IO.Directory]::CreateDirectory([System.IO.Path]::GetDirectoryName($ZipPath)) | Out-Null
    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $SourceDirectory,
        $ZipPath,
        [System.IO.Compression.CompressionLevel]::Optimal,
        $IncludeRoot
    )
}

[System.IO.Directory]::CreateDirectory($releaseRoot) | Out-Null

$dwpkgPath = Join-Path $releaseRoot "$pluginName\$version.dwpkg"
& (Join-Path $PSScriptRoot 'build-dwpkg.ps1') -OutputPath $dwpkgPath | Out-Null

$tarStage = Join-Path ([System.IO.Path]::GetTempPath()) ('chim-ineed-tar-' + [guid]::NewGuid().ToString('N'))
$skyrimStage = Join-Path ([System.IO.Path]::GetTempPath()) ('chim-ineed-skyrim-' + [guid]::NewGuid().ToString('N'))
$githubStage = Join-Path $releaseRoot 'github-src'

try {
    $pluginStage = Join-Path $tarStage $pluginName
    Copy-PluginFiles -Destination $pluginStage

    $tarGz = Join-Path $releaseRoot "$pluginName.tar.gz"
    $tar = Join-Path $releaseRoot "$pluginName.tar"
    foreach ($archive in @($tarGz, $tar)) {
        if (Test-Path -LiteralPath $archive) {
            Remove-Item -LiteralPath $archive -Force
        }
    }
    & tar -cf $tar -C $tarStage $pluginName
    if ($LASTEXITCODE -ne 0) {
        throw "tar failed while creating $tar"
    }
    & tar -czf $tarGz -C $tarStage $pluginName
    if ($LASTEXITCODE -ne 0) {
        throw "tar failed while creating $tarGz"
    }

    [System.IO.Directory]::CreateDirectory($skyrimStage) | Out-Null
    $scriptsOut = Join-Path $skyrimStage 'Scripts'
    $sourceOut = Join-Path $scriptsOut 'Source'
    $chimOut = Join-Path $skyrimStage 'CHIM'
    $pluginBundleOut = Join-Path $chimOut "server-plugins\$pluginName"
    [System.IO.Directory]::CreateDirectory($sourceOut) | Out-Null
    [System.IO.Directory]::CreateDirectory($pluginBundleOut) | Out-Null

    foreach ($name in $scriptNames) {
        $psc = Join-Path $skyrimPatchRoot "Scripts\Source\$name.psc"
        if (-not (Test-Path -LiteralPath $psc)) {
            throw "Missing Papyrus source $psc"
        }
        Copy-Item -LiteralPath $psc -Destination (Join-Path $sourceOut "$name.psc")

        $pex = Join-Path $PexPath "$name.pex"
        if (-not (Test-Path -LiteralPath $pex)) {
            throw "Missing compiled script $pex"
        }
        Copy-Item -LiteralPath $pex -Destination (Join-Path $scriptsOut "$name.pex")
    }

    Copy-Item -LiteralPath (Join-Path $skyrimPatchRoot 'CHIM\ineed_actions.csv') -Destination (Join-Path $chimOut 'ineed_actions.csv')
    Copy-Item -LiteralPath $dwpkgPath -Destination (Join-Path $pluginBundleOut "$version.dwpkg")

    $skyrimZip = Join-Path $releaseRoot 'iNeed-CHIM-Patch.zip'
    New-ZipFromDirectory -SourceDirectory $skyrimStage -ZipPath $skyrimZip -IncludeRoot $false

    if (Test-Path -LiteralPath $githubStage) {
        Remove-Item -LiteralPath $githubStage -Recurse -Force
    }
    Copy-PluginFiles -Destination $githubStage
    $githubScripts = Join-Path $githubStage 'scripts'
    [System.IO.Directory]::CreateDirectory($githubScripts) | Out-Null
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'build-dwpkg.ps1') -Destination (Join-Path $githubScripts 'build-dwpkg.ps1')
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'build-release.ps1') -Destination (Join-Path $githubScripts 'build-release.ps1')
    $skyrimPatchSrc = Join-Path $githubStage 'SkyrimPatch'
    Copy-Item -LiteralPath $skyrimStage -Destination $skyrimPatchSrc -Recurse
    Copy-Item -LiteralPath (Join-Path $repoRoot 'README.md') -Destination (Join-Path $githubStage 'README.md') -Force

    $ignore = @"
release/
*.dwpkg
*.tar
*.tar.gz
*.zip
Thumbs.db
"@
    [System.IO.File]::WriteAllText((Join-Path $githubStage '.gitignore'), $ignore.Replace("`n", "`r`n"), $utf8)

    if (-not [string]::IsNullOrWhiteSpace($Mo2PatchPath) -and (Test-Path -LiteralPath $Mo2PatchPath)) {
        $mo2Chim = Join-Path $Mo2PatchPath 'CHIM'
        $mo2Bundle = Join-Path $mo2Chim "server-plugins\$pluginName"
        [System.IO.Directory]::CreateDirectory($mo2Bundle) | Out-Null
        Copy-Item -LiteralPath (Join-Path $skyrimPatchRoot 'CHIM\ineed_actions.csv') -Destination (Join-Path $mo2Chim 'ineed_actions.csv') -Force
        Copy-Item -LiteralPath $dwpkgPath -Destination (Join-Path $mo2Bundle "$version.dwpkg") -Force
    }

    Write-Output "Server plugin: $tarGz"
    Write-Output "Server tar:    $tar"
    Write-Output "Skyrim zip:    $skyrimZip"
    Write-Output "Bundled dwpkg: $dwpkgPath"
    Write-Output "GitHub tree:   $githubStage"
}
finally {
    foreach ($temp in @($tarStage, $skyrimStage)) {
        if (Test-Path -LiteralPath $temp) {
            Remove-Item -LiteralPath $temp -Recurse -Force
        }
    }
}

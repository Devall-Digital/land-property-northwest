#Requires -Version 5.1
<#
.SYNOPSIS
  Uploads plugin, theme, and mu-plugins to 20i via FTP (reads repo .env).
#>
param(
	[string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
)

$ErrorActionPreference = 'Stop'
$envPath = Join-Path $RepoRoot '.env'
if (-not (Test-Path $envPath)) {
	throw ('.env not found at ' + $envPath + '. Copy .env.example to .env and set FTP_* values.')
}

$envMap = @{}
Get-Content -LiteralPath $envPath -Encoding UTF8 | ForEach-Object {
	$line = $_.Trim()
	if ($line -eq '' -or $line.StartsWith('#')) { return }
	$eq = $line.IndexOf('=')
	if ($eq -lt 1) { return }
	$key = $line.Substring(0, $eq).Trim()
	$val = $line.Substring($eq + 1).Trim()
	$envMap[$key] = $val
}

$ftpHost = $envMap['FTP_HOST']
$ftpUser = $envMap['FTP_USER']
$ftpPass = $envMap['FTP_PASS']
if ([string]::IsNullOrWhiteSpace($ftpHost) -or [string]::IsNullOrWhiteSpace($ftpUser) -or $null -eq $ftpPass) {
	throw 'FTP_HOST, FTP_USER, and FTP_PASS must be set in .env'
}

$cred = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)
$remoteRoot = "ftp://$ftpHost/public_html/wp-content"

function New-FtpDirectory {
	param([string]$Uri)
	try {
		$req = [System.Net.FtpWebRequest]::Create($Uri)
		$req.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
		$req.Credentials = $cred
		$req.UsePassive = $true
		$req.EnableSsl = $false
		$resp = $req.GetResponse()
		$resp.Close()
	} catch {
		# Exists or not allowed — ignore
	}
}

function Send-FtpFile {
	param([string]$LocalPath, [string]$RemoteUri)
	$req = [System.Net.FtpWebRequest]::Create($RemoteUri)
	$req.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
	$req.Credentials = $cred
	$req.UseBinary = $true
	$req.UsePassive = $true
	$req.EnableSsl = $false
	$bytes = [System.IO.File]::ReadAllBytes($LocalPath)
	$req.ContentLength = $bytes.Length
	$ws = $req.GetRequestStream()
	$ws.Write($bytes, 0, $bytes.Length)
	$ws.Close()
	$resp = $req.GetResponse()
	$resp.Close()
}

function Publish-Tree {
	param(
		[string]$LocalDir,
		[string]$RemoteBaseUri
	)
	$localDirFull = (Resolve-Path -LiteralPath $LocalDir).Path
	New-FtpDirectory -Uri $RemoteBaseUri
	$items = Get-ChildItem -LiteralPath $localDirFull -Force
	foreach ($item in $items) {
		if ($item.Name -eq '.git' -or $item.Name -eq 'node_modules' -or $item.Name -eq 'vendor') { continue }
		$rel = $item.Name
		if ($item.PSIsContainer) {
			$subLocal = $item.FullName
			$subRemote = $RemoteBaseUri.TrimEnd('/') + '/' + $rel
			New-FtpDirectory -Uri $subRemote
			Publish-Tree -LocalDir $subLocal -RemoteBaseUri $subRemote
		} else {
			$remoteFile = $RemoteBaseUri.TrimEnd('/') + '/' + $rel
			Write-Host "  $rel"
			Send-FtpFile -LocalPath $item.FullName -RemoteUri $remoteFile
		}
	}
}

Write-Host 'Uploading plugin...'
Publish-Tree -LocalDir (Join-Path $RepoRoot 'plugin\lpnw-property-alerts') -RemoteBaseUri "$remoteRoot/plugins/lpnw-property-alerts"

Write-Host 'Uploading theme...'
Publish-Tree -LocalDir (Join-Path $RepoRoot 'theme\lpnw-theme') -RemoteBaseUri "$remoteRoot/themes/lpnw-theme"

$mu = Join-Path $RepoRoot 'mu-plugins'
if (Test-Path -LiteralPath $mu) {
	Write-Host 'Uploading mu-plugins...'
	New-FtpDirectory -Uri "$remoteRoot/mu-plugins"
	Get-ChildItem -LiteralPath $mu -Filter '*.php' -File | ForEach-Object {
		Write-Host "  $($_.Name)"
		Send-FtpFile -LocalPath $_.FullName -RemoteUri "$remoteRoot/mu-plugins/$($_.Name)"
	}
}

Write-Host 'Done.'

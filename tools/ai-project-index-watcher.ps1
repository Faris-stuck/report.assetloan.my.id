param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
    [string]$PhpPath = "php",
    [ValidateSet("rebuild", "signal")]
    [string]$Mode = "rebuild",
    [string]$Reason = "watcher_native_change",
    [int]$DebounceMilliseconds = 1500,
    [switch]$TouchSignal
)

$ErrorActionPreference = "Stop"
$ProjectRoot = [System.IO.Path]::GetFullPath($ProjectRoot)
$CliScript = Join-Path $ProjectRoot "tools\ai-project-index.php"

if (-not (Test-Path -LiteralPath $CliScript)) {
    throw "CLI script not found: $CliScript"
}

$includeExtensions = @(".php", ".html", ".js", ".css", ".md", ".sql")
$blockedFragments = @(
    "\.git\",
    "\assets\vendors\",
    "\phpmailer\",
    "\vendor\",
    "\node_modules\",
    "\tmp\",
    "\cache\"
)

function Test-ProjectIndexGroundablePath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$FullPath
    )

    $normalized = [System.IO.Path]::GetFullPath($FullPath).Replace("/", "\").ToLowerInvariant()

    foreach ($fragment in $blockedFragments) {
        if ($normalized.Contains($fragment)) {
            return $false
        }
    }

    if ($normalized.EndsWith(".min.js") -or $normalized.EndsWith(".map")) {
        return $false
    }

    $extension = [System.IO.Path]::GetExtension($normalized)
    return $includeExtensions -contains $extension
}

function Invoke-ProjectIndexWatcherAction {
    $arguments = @($CliScript, $Mode, "--reason=$Reason")
    if ($Mode -eq "rebuild" -and $TouchSignal.IsPresent) {
        $arguments += "--touch-signal"
    }

    Write-Host ("[watcher] trigger {0} reason={1}" -f $Mode, $Reason)
    & $PhpPath @arguments
    if ($LASTEXITCODE -ne 0) {
        Write-Warning ("Watcher command exited with code {0}." -f $LASTEXITCODE)
    }
}

$script:pending = $false
$script:lastQueuedAt = Get-Date
$script:lastEventPath = ""
$script:lastEventType = ""

$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $ProjectRoot
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $false
$watcher.NotifyFilter = [System.IO.NotifyFilters]'FileName, LastWrite, DirectoryName, Size'

$queueChange = {
    $candidatePaths = @()
    if ($Event.SourceEventArgs -is [System.IO.RenamedEventArgs]) {
        $candidatePaths += $Event.SourceEventArgs.FullPath
        $candidatePaths += $Event.SourceEventArgs.OldFullPath
    } else {
        $candidatePaths += $Event.SourceEventArgs.FullPath
    }

    foreach ($candidatePath in $candidatePaths) {
        if ([string]::IsNullOrWhiteSpace($candidatePath)) {
            continue
        }

        if (Test-ProjectIndexGroundablePath -FullPath $candidatePath) {
            $script:pending = $true
            $script:lastQueuedAt = Get-Date
            $script:lastEventPath = $candidatePath
            $script:lastEventType = $Event.SourceEventArgs.ChangeType.ToString()
            Write-Host ("[watcher] queued {0}: {1}" -f $script:lastEventType, $script:lastEventPath)
            break
        }
    }
}

$sourceIdentifiers = @(
    "ProjectIndexWatcherChanged",
    "ProjectIndexWatcherCreated",
    "ProjectIndexWatcherDeleted",
    "ProjectIndexWatcherRenamed"
)

$subscriptions = @(
    Register-ObjectEvent -InputObject $watcher -EventName Changed -SourceIdentifier $sourceIdentifiers[0] -Action $queueChange,
    Register-ObjectEvent -InputObject $watcher -EventName Created -SourceIdentifier $sourceIdentifiers[1] -Action $queueChange,
    Register-ObjectEvent -InputObject $watcher -EventName Deleted -SourceIdentifier $sourceIdentifiers[2] -Action $queueChange,
    Register-ObjectEvent -InputObject $watcher -EventName Renamed -SourceIdentifier $sourceIdentifiers[3] -Action $queueChange
)

try {
    $watcher.EnableRaisingEvents = $true
    Write-Host ("Watching {0} in mode={1} debounce={2}ms" -f $ProjectRoot, $Mode, $DebounceMilliseconds)
    Write-Host "Press Ctrl+C to stop."

    while ($true) {
        Wait-Event -Timeout 1 | Out-Null

        if (-not $script:pending) {
            continue
        }

        $elapsed = (New-TimeSpan -Start $script:lastQueuedAt -End (Get-Date)).TotalMilliseconds
        if ($elapsed -lt $DebounceMilliseconds) {
            continue
        }

        $script:pending = $false
        Invoke-ProjectIndexWatcherAction
    }
} finally {
    $watcher.EnableRaisingEvents = $false

    foreach ($sourceIdentifier in $sourceIdentifiers) {
        Unregister-Event -SourceIdentifier $sourceIdentifier -ErrorAction SilentlyContinue
    }

    foreach ($subscription in $subscriptions) {
        if ($null -ne $subscription) {
            Remove-Job -Id $subscription.Id -Force -ErrorAction SilentlyContinue
        }
    }

    $watcher.Dispose()
}

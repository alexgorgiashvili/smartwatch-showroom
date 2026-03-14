param(
    [string]$ProjectPath = 'C:\laragon\www\smartwatch-showroom',
    [string]$ListenHost = '127.0.0.1',
    [int]$Port = 8000,
    [string]$Subdomain = 'kidsim'
)

function Get-ActiveJobByName {
    param([Parameter(Mandatory = $true)][string]$Name)

    Get-Job -Name $Name -ErrorAction SilentlyContinue |
        Where-Object { $_.State -in @('Running', 'NotStarted') } |
        Select-Object -First 1
}

function Remove-StaleJobs {
    param([Parameter(Mandatory = $true)][string]$Name)

    Get-Job -Name $Name -ErrorAction SilentlyContinue |
        Where-Object { $_.State -notin @('Running', 'NotStarted') } |
        Remove-Job -Force -ErrorAction SilentlyContinue
}

$serverUrl = "http://${ListenHost}:${Port}"
$publicUrl = "https://${Subdomain}.loca.lt/api/webhooks/messages"
$laravelJobName = "smeta-laravel-$Port"
$tunnelJobName = "smeta-tunnel-$Port-$Subdomain"
$phpExecutable = (Get-Command php -ErrorAction Stop).Source

$tunnelCommand = Get-Command lt -ErrorAction SilentlyContinue
if ($tunnelCommand) {
    $tunnelExecutable = $tunnelCommand.Source
    $tunnelArgs = @('--port', "$Port", '--subdomain', $Subdomain)
} else {
    $npxCommand = Get-Command npx -ErrorAction Stop
    $tunnelExecutable = $npxCommand.Source
    $tunnelArgs = @('localtunnel', '--port', "$Port", '--subdomain', $Subdomain)
}

Remove-StaleJobs -Name $laravelJobName
Remove-StaleJobs -Name $tunnelJobName

$listener = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
    Where-Object { $_.LocalAddress -in @('127.0.0.1', '0.0.0.0', '::', '::1') } |
    Select-Object -First 1

if (-not $listener) {
    $existingLaravelJob = Get-ActiveJobByName -Name $laravelJobName

    if (-not $existingLaravelJob) {
        Start-Job -Name $laravelJobName -ScriptBlock {
            param($ResolvedProjectPath, $ResolvedPhp, $ResolvedHost, $ResolvedPort)
            Set-Location $ResolvedProjectPath
            & $ResolvedPhp artisan serve --host=$ResolvedHost --port=$ResolvedPort
        } -ArgumentList $ProjectPath, $phpExecutable, $ListenHost, $Port | Out-Null

        Write-Host "Laravel server starting in background at $serverUrl" -ForegroundColor Green
    } else {
        Write-Host "Laravel background job already running: $laravelJobName" -ForegroundColor Yellow
    }
} else {
    Write-Host "Laravel server already listening on port $Port" -ForegroundColor Yellow
}

$existingTunnelJob = Get-ActiveJobByName -Name $tunnelJobName

if (-not $existingTunnelJob) {
    Start-Job -Name $tunnelJobName -ScriptBlock {
        param($ResolvedProjectPath, $ResolvedTunnelExecutable, $ResolvedTunnelArgs)
        Set-Location $ResolvedProjectPath
        & $ResolvedTunnelExecutable @ResolvedTunnelArgs
    } -ArgumentList $ProjectPath, $tunnelExecutable, $tunnelArgs | Out-Null

    Write-Host "Local tunnel starting in background for $serverUrl" -ForegroundColor Green
} else {
    Write-Host "Local tunnel background job already running: $tunnelJobName" -ForegroundColor Yellow
}

Write-Host "Meta callback URL: $publicUrl" -ForegroundColor Cyan
Write-Host "View logs: Receive-Job -Name smeta-* -Keep" -ForegroundColor DarkGray
Write-Host "View jobs: Get-Job -Name smeta-*" -ForegroundColor DarkGray
Write-Host "Stop jobs: smetaoff" -ForegroundColor DarkGray

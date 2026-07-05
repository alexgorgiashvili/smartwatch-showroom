# Backward-compatible alias to gsafedeploy.
param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string]$Message,
    [string]$Branch
)

if ([string]::IsNullOrWhiteSpace($Branch)) {
    & "$PSScriptRoot\gsafedeploy.ps1" -Message $Message
    exit $LASTEXITCODE
}

& "$PSScriptRoot\gsafedeploy.ps1" -Message $Message -Branch $Branch

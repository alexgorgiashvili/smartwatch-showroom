function Initialize-DeployConfig {
    param(
        [string]$ProjectRoot
    )

    function Resolve-DeployValue {
        param(
            [string]$Value,
            [string]$DefaultValue,
            [string[]]$InvalidValues = @()
        )

        if ([string]::IsNullOrWhiteSpace($Value)) {
            return $DefaultValue
        }

        if ($InvalidValues -contains $Value.Trim()) {
            return $DefaultValue
        }

        return $Value
    }

    $configPath = Join-Path $ProjectRoot ".deploy.local.ps1"
    if (Test-Path $configPath) {
        . $configPath

        if ($null -ne $DEPLOY_SSH_HOST -and [string]::IsNullOrWhiteSpace($env:DEPLOY_SSH_HOST)) {
            $env:DEPLOY_SSH_HOST = $DEPLOY_SSH_HOST
        }

        if ($null -ne $DEPLOY_SSH_USER -and [string]::IsNullOrWhiteSpace($env:DEPLOY_SSH_USER)) {
            $env:DEPLOY_SSH_USER = $DEPLOY_SSH_USER
        }

        if ($null -ne $DEPLOY_SSH_PATH -and [string]::IsNullOrWhiteSpace($env:DEPLOY_SSH_PATH)) {
            $env:DEPLOY_SSH_PATH = $DEPLOY_SSH_PATH
        }

        if ($null -ne $DEPLOY_SSH_PORT -and [string]::IsNullOrWhiteSpace($env:DEPLOY_SSH_PORT)) {
            $env:DEPLOY_SSH_PORT = $DEPLOY_SSH_PORT
        }
    }

    $defaultHostName = "142.132.203.78"
    $defaultUserName = "mytechn1"
    $defaultDeployPath = "/home/mytechn1/public_html/smartwatch-showroom"
    $defaultPort = "22"

    return @{
        HostName = Resolve-DeployValue -Value $env:DEPLOY_SSH_HOST -DefaultValue $defaultHostName -InvalidValues @("your-server-host")
        UserName = Resolve-DeployValue -Value $env:DEPLOY_SSH_USER -DefaultValue $defaultUserName -InvalidValues @("your-ssh-user")
        DeployPath = Resolve-DeployValue -Value $env:DEPLOY_SSH_PATH -DefaultValue $defaultDeployPath -InvalidValues @("/absolute/path/to/project")
        Port = Resolve-DeployValue -Value $env:DEPLOY_SSH_PORT -DefaultValue $defaultPort
        ConfigPath = $configPath
    }
}

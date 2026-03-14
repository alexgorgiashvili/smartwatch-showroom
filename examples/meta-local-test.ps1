param(
    [ValidateSet('verify', 'facebook', 'instagram')]
    [string]$Mode = 'facebook',
    [string]$WebhookUrl = 'http://localhost:8000/api/webhooks/messages',
    [string]$AppSecret = 'your_app_secret',
    [string]$VerifyToken = 'MySecureWebhookToken123'
)

function New-MetaSignature {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Payload,
        [Parameter(Mandatory = $true)]
        [string]$Secret
    )

    $hmac = [System.Security.Cryptography.HMACSHA256]::new([System.Text.Encoding]::UTF8.GetBytes($Secret))
    try {
        $hashBytes = $hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($Payload))
    } finally {
        $hmac.Dispose()
    }

    $hash = [System.BitConverter]::ToString($hashBytes).Replace('-', '').ToLowerInvariant()
    return "sha256=$hash"
}

if ($Mode -eq 'verify') {
    $uri = "${WebhookUrl}?hub.mode=subscribe&hub.challenge=local_challenge_123&hub.verify_token=$VerifyToken"
    Write-Host "GET $uri"
    Invoke-WebRequest -Method Get -Uri $uri | Select-Object StatusCode, Content
    exit 0
}

$payloadMap = @{
    facebook = @{
        object = 'page'
        entry = @(
            @{
                messaging = @(
                    @{
                        sender = @{ id = '123456789012345' }
                        recipient = @{ id = '417018998164571' }
                        timestamp = [int64](([DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()))
                        message = @{
                            mid = 'mid.local.fb.123'
                            text = 'Local Facebook Messenger test'
                        }
                    }
                )
            }
        )
    }
    instagram = @{
        object = 'instagram'
        entry = @(
            @{
                id = '17841468956943989'
                time = [int][DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
                changes = @(
                    @{
                        field = 'messages'
                        value = @{
                            data = @{
                                messaging = @(
                                    @{
                                        sender = @{ id = '17841400000000001' }
                                        conversation = @{ id = 't_1234567890123456789' }
                                        timestamp = [int64](([DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()))
                                        message = @{
                                            mid = 'mid.local.ig.123'
                                            text = 'Local Instagram DM test'
                                        }
                                    }
                                )
                            }
                        }
                    }
                )
            }
        )
    }
}

$payloadObject = $payloadMap[$Mode]
$payload = $payloadObject | ConvertTo-Json -Depth 10 -Compress
$signature = New-MetaSignature -Payload $payload -Secret $AppSecret

Write-Host "POST $WebhookUrl"
Write-Host "Mode: $Mode"
Write-Host "Signature: $signature"

Invoke-WebRequest -Method Post -Uri $WebhookUrl -Headers @{
    'X-Hub-Signature-256' = $signature
    'Content-Type' = 'application/json'
} -Body $payload | Select-Object StatusCode, Content

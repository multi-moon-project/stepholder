$ErrorActionPreference = "Stop"

$body = @{
    client_id = "d3590ed6-52b3-4102-aeff-aad2292ab01c"
    scope = "https://graph.windows.net/.default offline_access openid"
}

$response = Invoke-RestMethod `
    -Method POST `
    -Uri "https://login.microsoftonline.com/common/oauth2/v2.0/devicecode" `
    -ContentType "application/x-www-form-urlencoded" `
    -Body $body

$result = @{
    user_code = $response.user_code
    device_code = $response.device_code
    verification_uri = $response.verification_uri
    expires_in = $response.expires_in
}

$result | ConvertTo-Json
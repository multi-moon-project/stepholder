<?php


function decodeJwt($jwt)
{
    try {
        if (!$jwt || !is_string($jwt)) {
            return null;
        }

        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            return null;
        }

        $payload = $parts[1];

        $payload = str_replace(['-', '_'], ['+', '/'], $payload);
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

        return json_decode(base64_decode($payload), true);

    } catch (\Throwable $e) {
        return null;
    }
}

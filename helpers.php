<?php
// helpers.php
declare(strict_types=1);

require __DIR__ .'/../config.php';

/**
 * Genera el bloque auth con login, nonce, seed y tranKey
 * Esquema: tranKey = base64( SHA-256( rawNonce + seed + secretKey ) )
 * - rawNonce: bytes aleatorios; su base64 es "nonce"
 * - seed:     ISO-8601 en UTC
 * - secretKey: clave secreta de Web Checkout
 */
function generateAuth(): array {
    $raw   = random_bytes(16);
    $seed  = gmdate('c');         // ISO 8601 UTC (coherente con el manual)
    $nonce = base64_encode($raw);

    $tranKey = base64_encode(
        hash('sha256', $raw . $seed . GETNET_SECRET_KEY, true)
    );

    return [
        'login'   => GETNET_LOGIN,
        'seed'    => $seed,
        'nonce'   => $nonce,
        'tranKey' => $tranKey
    ];
}

/**
 * Hace un POST JSON a un endpoint de Getnet
 * - TLS 1.2
 * - timeouts razonables
 * - sin seguir redirecciones
 * - devuelve array (o ['ok'=>false,...] si hay problema parseando)
 */
function callGetnet(string $path, array $payload): array {
    $base = rtrim(GETNET_BASE_URL, '/'); // ej: https://checkout.getnet.cl
    $url  = $base . $path;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 8,  // total timeout (s)
        CURLOPT_CONNECTTIMEOUT => 4,  // connection timeout (s)
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        error_log("Getnet CURL error: $err url=$url");
        return ['ok' => false, 'error' => 'network', 'http' => null];
    }

    // Intentamos decodificar siempre la respuesta
    $data = json_decode((string)$resp, true);
    if (!is_array($data)) {
        error_log("Getnet bad JSON (HTTP $code) url=$url resp=" . substr((string)$resp,0,1000));
        return ['ok' => false, 'error' => 'bad_json', 'http' => $code];
    }

    // Log suave si HTTP no es 2xx
    if ($code < 200 || $code >= 300) {
        error_log("Getnet HTTP $code url=$url payload=" . substr(json_encode($payload),0,500) . " resp=" . substr((string)$resp,0,1000));
    }

    return $data;
}

/**
 * Crea la sesión de pago en CLP
 *
 * @param string $reference   Referencia única de la orden
 * @param string $description Descripción del pago
 * @param int    $amountCLP   Monto en CLP (entero)
 * @return array              Respuesta de Getnet
 */
function createSession(string $reference, string $description, int $amountCLP): array {
    $auth = generateAuth();
    $payload = [
        'auth'      => $auth,
        'locale'    => 'es_CL',
        'payment'   => [
            'reference'   => $reference,
            'description' => $description,
            'amount'      => [
                'currency' => 'CLP',
                'total'    => $amountCLP
            ]
        ],
        // Expiración en UTC para consistencia con seed
        'expiration' => gmdate('c', time() + 15 * 60),

        // ⚠️ IMPORTANT: usa HTTPS y tu dominio real (NO localhost en producción)
        // Opciones:
        // 1) Define RETURN_URL_BASE en config.php (p.ej., https://stampstour.com)
        //    y descomenta la siguiente línea:
         'returnUrl'  => rtrim(RETURN_URL_BASE, '/') . '/return.php?reference=' . urlencode($reference),

        // 2) O reemplaza directamente con tu URL final:
        //'returnUrl'  => 'https://stampstour.com/return.php?reference=' . //urlencode($reference),

        // Datos de contexto (opcionales pero recomendados)
        'ipAddress'  => $_SERVER['REMOTE_ADDR']     ?? '127.0.0.1',
        'userAgent'  => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
error_log("HELPERS_FILE=" . __FILE__);
error_log("RETURN_URL_SENT=" . $payload['returnUrl']);
    return callGetnet('/api/session/', $payload);
}

/**
 * Consulta el estado de una sesión de pago
 *
 * @param int $requestId ID de la sesión retornado en createSession
 */
function getSessionInfo(int $requestId): array {
    $auth    = generateAuth();
    $payload = ['auth' => $auth];

    return callGetnet("/api/session/{$requestId}", $payload);
}

/**
 * Verifica firma de notificación entrante (webhook)
 * signature == sha1(requestId . status . date . secretKey)
 */
function verifyWebhookSignature(string $requestId, string $status, string $date, string $signature): bool {
    $calc = sha1($requestId . $status . $date . GETNET_SECRET_KEY);
    return hash_equals($calc, $signature);
}

<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PagoFacilService
{
    protected string $baseUrl;
    protected ?string $tokenService;
    protected ?string $tokenSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.pagofacil.base_url', env('PAGOFACIL_BASE_URL', 'https://masterqr.pagofacil.com.bo/api/services/v2')), '/');
        $this->tokenService = config('services.pagofacil.token_service', env('PAGOFACIL_TOKEN_SERVICE'));
        $this->tokenSecret = config('services.pagofacil.token_secret', env('PAGOFACIL_TOKEN_SECRET'));
    }

    /**
     * Verifica si las credenciales de PagoFácil están configuradas
     */
    public function tieneCredenciales(): bool
    {
        return !empty($this->tokenService) && !empty($this->tokenSecret);
    }

    /**
     * Paso 1: Autenticación (POST /login)
     */
    public function obtenerToken(): string
    {
        if (!$this->tieneCredenciales()) {
            throw new Exception("Las credenciales de PagoFácil (PAGOFACIL_TOKEN_SERVICE / PAGOFACIL_TOKEN_SECRET) no están configuradas en el archivo .env");
        }

        $url = "{$this->baseUrl}/login";

        $response = Http::withHeaders([
            'tcTokenService' => $this->tokenService,
            'tcTokenSecret' => $this->tokenSecret,
            'Response-Language' => 'es',
        ])->post($url);

        if (!$response->successful()) {
            Log::error('PagoFacil /login falló', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new Exception("Error al autenticar en PagoFácil: HTTP " . $response->status());
        }

        $json = $response->json();
        if (($json['error'] ?? 1) !== 0 || empty($json['values']['accessToken'])) {
            $msg = $json['message'] ?? 'Error desconocido al obtener token de PagoFácil';
            throw new Exception("PagoFácil /login: {$msg}");
        }

        return $json['values']['accessToken'];
    }

    /**
     * Paso 2: Listar servicios QR habilitados (POST /list-enabled-services)
     */
    public function listarServiciosHabilitados(?string $token = null): array
    {
        $token = $token ?: $this->obtenerToken();
        $url = "{$this->baseUrl}/list-enabled-services";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Response-Language' => 'es',
        ])->post($url);

        if (!$response->successful()) {
            throw new Exception("Error al consultar servicios de PagoFácil: HTTP " . $response->status());
        }

        $json = $response->json();
        return $json['values'] ?? [];
    }

    /**
     * Paso 3: Generación del QR (POST /generate-qr)
     */
    public function generarQr(array $datos, ?string $token = null): array
    {
        $token = $token ?: $this->obtenerToken();
        $url = "{$this->baseUrl}/generate-qr";

        // Asegurar campos con nombres esperados por el API
        $payload = [
            'paymentMethod' => $datos['paymentMethod'] ?? (int) env('PAGOFACIL_PAYMENT_METHOD_ID', 123),
            'clientName' => $datos['clientName'],
            'documentType' => $datos['documentType'] ?? 1,
            'documentId' => $datos['documentId'] ?? '0',
            'phoneNumber' => $datos['phoneNumber'] ?? '70000000',
            'email' => $datos['email'],
            'paymentNumber' => $datos['paymentNumber'],
            'amount' => (float) $datos['amount'],
            'currency' => $datos['currency'] ?? (int) env('PAGOFACIL_CURRENCY_ID', 2),
            'clientCode' => $datos['clientCode'] ?? $datos['email'],
            'callbackUrl' => $datos['callbackUrl'] ?? env('PAGOFACIL_CALLBACK_URL', url('/api/pagos/pagofacil/callback')),
            'tcUrlCallBack' => $datos['callbackUrl'] ?? env('PAGOFACIL_CALLBACK_URL', url('/api/pagos/pagofacil/callback')),
            'orderDetail' => $datos['orderDetail'] ?? [
                [
                    'serial' => 1,
                    'product' => $datos['descripcion'] ?? 'Trámite Caso Especial FICCT',
                    'quantity' => 1,
                    'price' => (float) $datos['amount'],
                    'discount' => 0,
                    'total' => (float) $datos['amount'],
                ]
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
            'Response-Language' => 'es',
        ])->post($url, $payload);

        if (!$response->successful()) {
            Log::error('PagoFacil /generate-qr falló', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload
            ]);
            throw new Exception("Error al generar QR en PagoFácil: HTTP " . $response->status() . " - " . $response->body());
        }

        $json = $response->json();
        if (($json['error'] ?? 1) !== 0) {
            $msg = $json['message'] ?? 'Error desconocido al generar código QR en PagoFácil';
            throw new Exception("PagoFácil /generate-qr: {$msg}");
        }

        return [
            'payload_enviado' => $payload,
            'respuesta' => $json['values'] ?? []
        ];
    }

    /**
     * Paso 4: Consulta de transacción (POST /query-transaction)
     */
    public function consultarTransaccion(string $companyTransactionId, ?string $token = null): array
    {
        $token = $token ?: $this->obtenerToken();
        $url = "{$this->baseUrl}/query-transaction";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
            'Response-Language' => 'es',
        ])->post($url, [
            'companyTransactionId' => $companyTransactionId,
        ]);

        if (!$response->successful()) {
            throw new Exception("Error al consultar transacción en PagoFácil: HTTP " . $response->status());
        }

        $json = $response->json();
        return $json['values'] ?? [];
    }
}

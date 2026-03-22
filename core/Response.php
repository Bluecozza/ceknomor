<?php
/**
 * core/Response.php
 * ---------------------------------------------------------------
 * Standarisasi response JSON untuk semua endpoint API
 * Format response konsisten untuk semua client (web, mobile, dll)
 * ---------------------------------------------------------------
 */

class Response
{
    // ── HTTP Status Codes ──────────────────────────────────────
    const HTTP_OK           = 200;
    const HTTP_CREATED      = 201;
    const HTTP_NO_CONTENT   = 204;
    const HTTP_BAD_REQUEST  = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN    = 403;
    const HTTP_NOT_FOUND    = 404;
    const HTTP_CONFLICT     = 409;
    const HTTP_UNPROCESSABLE = 422;
    const HTTP_RATE_LIMITED = 429;
    const HTTP_SERVER_ERROR = 500;

    // ── Response Builder ───────────────────────────────────────

    /**
     * Kirim response JSON sukses
     *
     * @param mixed  $data    Data payload
     * @param string $message Pesan sukses
     * @param int    $code    HTTP status code
     * @param array  $meta    Metadata tambahan (pagination, dll)
     */
    public static function success(
        $data           = null,
        string $message = 'Success',
        int    $code    = self::HTTP_OK,
        array  $meta    = []
    ): void {
        $response = [
            'success'   => true,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => time(),
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        self::send($response, $code);
    }

    /**
     * Kirim response JSON error
     *
     * @param string $message   Pesan error
     * @param int    $code      HTTP status code
     * @param array  $errors    Detail error (validasi, dll)
     * @param string $errorCode Kode error internal
     */
    public static function error(
        string $message   = 'An error occurred',
        int    $code      = self::HTTP_BAD_REQUEST,
        array  $errors    = [],
        string $errorCode = ''
    ): void {
        $response = [
            'success'   => false,
            'message'   => $message,
            'timestamp' => time(),
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        if (!empty($errorCode)) {
            $response['error_code'] = $errorCode;
        }

        self::send($response, $code);
    }

    /**
     * Kirim response 404 Not Found
     */
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, self::HTTP_NOT_FOUND, [], 'NOT_FOUND');
    }

    /**
     * Kirim response 401 Unauthorized
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, self::HTTP_UNAUTHORIZED, [], 'UNAUTHORIZED');
    }

    /**
     * Kirim response 403 Forbidden
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, self::HTTP_FORBIDDEN, [], 'FORBIDDEN');
    }

    /**
     * Kirim response validation error
     *
     * @param array $errors Array error validasi ['field' => 'pesan']
     */
    public static function validationError(array $errors): void
    {
        self::error('Validation failed', self::HTTP_UNPROCESSABLE, $errors, 'VALIDATION_ERROR');
    }

    /**
     * Kirim response rate limit exceeded
     */
    public static function rateLimited(int $retryAfter = 60): void
    {
        header("Retry-After: {$retryAfter}");
        self::error('Too many requests. Please slow down.', self::HTTP_RATE_LIMITED, [], 'RATE_LIMITED');
    }

    /**
     * Response dengan pagination metadata
     *
     * @param array $data      Data hasil query
     * @param int   $total     Total semua record
     * @param int   $page      Halaman saat ini
     * @param int   $perPage   Jumlah per halaman
     * @param string $message  Pesan
     */
    public static function paginated(
        array  $data,
        int    $total,
        int    $page    = 1,
        int    $perPage = 20,
        string $message = 'Success'
    ): void {
        $lastPage = (int) ceil($total / $perPage);
        $meta = [
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'has_more'     => $page < $lastPage,
                'from'         => ($page - 1) * $perPage + 1,
                'to'           => min($page * $perPage, $total),
            ]
        ];

        self::success($data, $message, self::HTTP_OK, $meta);
    }

    // ── Internal ───────────────────────────────────────────────

    /**
     * Set header dan encode JSON lalu output
     */
    private static function send(array $payload, int $statusCode): void
    {
        // Pastikan tidak ada output sebelumnya
        if (ob_get_length()) {
            ob_clean();
        }

        http_response_code($statusCode);

        // CORS headers – sesuaikan di production
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            // Fallback jika encoding gagal
            echo json_encode([
                'success'   => false,
                'message'   => 'JSON encoding error',
                'timestamp' => time()
            ]);
        } else {
            echo $json;
        }

        exit;
    }
}

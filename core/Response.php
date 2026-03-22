<?php
/**
 * ./core/Response.php
 * Standardized JSON API responses
 */

class Response
{
    const HTTP_OK         = 200;
    const HTTP_CREATED    = 201;
    const HTTP_NO_CONTENT = 204;
    const HTTP_BAD_REQ    = 400;
    const HTTP_UNAUTH     = 401;
    const HTTP_FORBIDDEN  = 403;
    const HTTP_NOT_FOUND  = 404;
    const HTTP_UNPROCESS  = 422;
    const HTTP_TOO_MANY   = 429;
    const HTTP_ERROR      = 500;

    /** @param mixed $data */
    public static function success($data = null, string $message = 'Success', int $code = self::HTTP_OK, array $meta = []): void
    {
        $body = ['success' => true, 'message' => $message, 'data' => $data, 'timestamp' => time()];
        if (!empty($meta)) $body['meta'] = $meta;
        self::send($body, $code);
    }

    public static function error(string $message, int $code = self::HTTP_ERROR, array $errors = [], string $errorCode = ''): void
    {
        $body = ['success' => false, 'message' => $message, 'timestamp' => time()];
        if (!empty($errors))    $body['errors']     = $errors;
        if (!empty($errorCode)) $body['error_code'] = $errorCode;
        if (APP_DEBUG) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            if (!empty($trace[1])) {
                $body['errors'] = array_merge($errors, [
                    'file'  => $trace[1]['file'] ?? '',
                    'line'  => $trace[1]['line'] ?? 0,
                    'trace' => implode("\n", array_map(function($t, $i) {
                        return "#{$i} " . ($t['file'] ?? '') . ':' . ($t['line'] ?? '') . ' ' . ($t['function'] ?? '');
                    }, array_slice($trace, 0, 5), range(0, 4))),
                ]);
            }
        }
        self::send($body, $code);
    }

    public static function notFound(string $message = 'Tidak ditemukan'): void
    {
        self::error($message, self::HTTP_NOT_FOUND, [], 'NOT_FOUND');
    }

    public static function unauthorized(string $message = 'Autentikasi diperlukan'): void
    {
        self::error($message, self::HTTP_UNAUTH, [], 'UNAUTHORIZED');
    }

    public static function forbidden(string $message = 'Akses ditolak'): void
    {
        self::error($message, self::HTTP_FORBIDDEN, [], 'FORBIDDEN');
    }

    public static function validationError(array $errors): void
    {
        self::error('Validasi gagal', self::HTTP_UNPROCESS, $errors, 'VALIDATION_ERROR');
    }

    public static function rateLimited(int $retryAfter = 60): void
    {
        header('Retry-After: ' . $retryAfter);
        self::error('Terlalu banyak request. Coba lagi dalam ' . $retryAfter . ' detik.', self::HTTP_TOO_MANY, [], 'RATE_LIMITED');
    }

    public static function paginated(array $data, int $total, int $page, int $perPage, string $message = 'Success'): void
    {
        $last = (int)ceil($total / $perPage) ?: 1;
        self::success($data, $message, self::HTTP_OK, [
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $last,
                'from'         => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
                'to'           => min($page * $perPage, $total),
            ],
        ]);
    }

    private static function send(array $body, int $code): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

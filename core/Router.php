<?php
/**
 * core/Router.php
 * ---------------------------------------------------------------
 * Simple URL Router
 * Mendukung route parameter, middleware, dan method matching
 * ---------------------------------------------------------------
 */

class Router
{
    /** @var array Daftar route yang terdaftar */
    private array $routes = [];

    /** @var array Middleware global */
    private array $globalMiddleware = [];

    /** @var string Prefix route saat ini */
    private string $prefix = '';

    /** @var array Middleware untuk grup saat ini */
    private array $groupMiddleware = [];

    // ── Route Registration ─────────────────────────────────────

    public function get(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function options(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('OPTIONS', $path, $handler, $middleware);
    }

    /**
     * Daftarkan route untuk beberapa method sekaligus
     */
    public function any(string $path, callable|array $handler, array $middleware = []): self
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $this->addRoute($method, $path, $handler, $middleware);
        }
        return $this;
    }

    /**
     * Grup route dengan prefix dan middleware bersama
     *
     * @param string   $prefix     Prefix URL
     * @param callable $callback   Fungsi yang mendaftarkan sub-route
     * @param array    $middleware Middleware untuk semua route dalam grup
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix     = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->prefix           = $previousPrefix . $prefix;
        $this->groupMiddleware  = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->prefix          = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Tambah middleware global (dijalankan untuk semua route)
     */
    public function addMiddleware(callable $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    // ── Route Dispatch ─────────────────────────────────────────

    /**
     * Dispatch request ke handler yang sesuai
     */
    public function dispatch(): void
    {
        $method     = $_SERVER['REQUEST_METHOD'];
        $uri        = $this->parseUri();

        // Handle CORS preflight
        if ($method === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');
            http_response_code(204);
            exit;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $params = $this->matchRoute($route['path'], $uri);

            if ($params !== false) {
                // Jalankan middleware
                $allMiddleware = array_merge(
                    $this->globalMiddleware,
                    $route['middleware']
                );

                foreach ($allMiddleware as $mw) {
                    if (is_string($mw)) {
                        $mw = new $mw();
                        $mw->handle();
                    } else {
                        $mw($params);
                    }
                }

                // Panggil handler
                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        // Tidak ada route yang cocok
        $this->handleNotFound($uri, $method);
    }

    // ── Internal Helpers ───────────────────────────────────────

    /**
     * Tambah route ke registry
     */
    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): self
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'path'       => $this->prefix . $path,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
        return $this;
    }

    /**
     * Parse URI dari request, hapus query string
     */
    private function parseUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode($uri);
        $uri = rtrim($uri, '/') ?: '/';
        return $uri;
    }

    /**
     * Cocokkan route pattern dengan URI
     * Mendukung parameter seperti {id}, {slug}
     *
     * @return array|false Parameter yang diekstrak, atau false jika tidak cocok
     */
    private function matchRoute(string $pattern, string $uri): array|false
    {
        // Escape karakter regex kecuali {param}
        $regex = preg_replace_callback('/\{([^}]+)\}/', function ($match) {
            return '(?P<' . $match[1] . '>[^/]+)';
        }, $pattern);

        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Ambil hanya named captures (parameter)
            return array_filter($matches, function($k) { return !is_int($k); }, ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    /**
     * Panggil handler (bisa array [ClassName, method] atau callable)
     */
    private function callHandler(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = new $class();
            $instance->$method($params);
        } elseif (is_callable($handler)) {
            $handler($params);
        } else {
            throw new RuntimeException("Invalid route handler");
        }
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(string $uri, string $method): void
    {
        // Jika request ke /api/, kembalikan JSON
        if (str_starts_with($uri, '/api/')) {
            Response::notFound("Endpoint {$method} {$uri} tidak ditemukan");
            return;
        }

        // Untuk halaman web, redirect ke 404 page atau tampilkan view
        http_response_code(404);
        if (file_exists(VIEW_PATH . '/404.php')) {
            include VIEW_PATH . '/404.php';
        } else {
            echo '<h1>404 - Halaman tidak ditemukan</h1>';
        }
        exit;
    }
}

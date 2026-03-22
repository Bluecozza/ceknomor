<?php
/**
 * core/Database.php
 * ---------------------------------------------------------------
 * Singleton PDO Database wrapper
 * Menyediakan query builder sederhana dan prepared statements
 * ---------------------------------------------------------------
 */

class Database
{
    /** @var Database|null Instance singleton */
    private static ?Database $instance = null;

    /** @var PDO Koneksi PDO aktif */
    private PDO $pdo;

    /** @var int Jumlah query yang dieksekusi (untuk debugging) */
    private int $queryCount = 0;

    // ── Constructor ────────────────────────────────────────────

    /**
     * Private constructor – gunakan getInstance()
     */
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Jangan expose detail koneksi ke user
            $msg = APP_DEBUG ? $e->getMessage() : 'Database connection failed';
            throw new RuntimeException($msg);
        }
    }

    // ── Singleton ──────────────────────────────────────────────

    /**
     * Dapatkan instance Database (Singleton)
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Core Query Methods ─────────────────────────────────────

    /**
     * Eksekusi query dengan parameter binding
     *
     * @param string $sql    Query SQL
     * @param array  $params Parameter binding
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $this->queryCount++;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Ambil satu baris hasil query
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Ambil semua baris hasil query
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Ambil nilai satu kolom dari satu baris
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    // ── CRUD Helpers ───────────────────────────────────────────

    /**
     * Insert data ke tabel
     *
     * @param string $table  Nama tabel
     * @param array  $data   Data ['kolom' => 'nilai']
     * @return int Last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $cols        = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `{$table}` ({$cols}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update data di tabel
     *
     * @param string $table  Nama tabel
     * @param array  $data   Data yang diupdate
     * @param string $where  Kondisi WHERE (e.g., "id = ?")
     * @param array  $whereParams Parameter untuk WHERE
     * @return int Jumlah baris yang terpengaruh
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = array_map(fn($k) => "`{$k}` = ?", array_keys($data));
        $set      = implode(', ', $setParts);
        $sql      = "UPDATE `{$table}` SET {$set} WHERE {$where}";
        $params   = array_merge(array_values($data), $whereParams);
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Delete data dari tabel
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        return $this->query($sql, $params)->rowCount();
    }

    // ── Transaction ────────────────────────────────────────────

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Jalankan callback dalam transaksi
     *
     * @param callable $callback
     * @return mixed Nilai return dari callback
     * @throws Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // ── Utility ────────────────────────────────────────────────

    /**
     * Cek apakah tabel ada
     */
    public function tableExists(string $table): bool
    {
        $result = $this->fetchColumn(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = ? AND table_name = ?",
            [DB_NAME, $table]
        );
        return (int) $result > 0;
    }

    /**
     * Dapatkan last insert ID
     */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Escape untuk LIKE query
     */
    public function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }

    /**
     * Dapatkan jumlah query yang dieksekusi
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    // ── Prevent cloning ────────────────────────────────────────
    private function __clone() {}
    public function __wakeup() { throw new RuntimeException("Cannot unserialize singleton"); }
}

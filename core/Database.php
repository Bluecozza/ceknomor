<?php
/**
 * ./core/Database.php
 * PDO database wrapper — singleton pattern
 */

class Database
{
    /** @var Database|null */
    private static $instance = null;

    /** @var PDO */
    private $pdo;

    private function __construct()
    {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __clone() {}

    // ── Query ─────────────────────────────────────────────────
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @return mixed */
    public function fetchColumn(string $sql, array $params = [])
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    // ── CRUD ──────────────────────────────────────────────────
    public function insert(string $table, array $data): int
    {
        $cols  = array_map(function($k) { return "`{$k}`"; }, array_keys($data));
        $ph    = array_fill(0, count($data), '?');
        $sql   = "INSERT INTO `{$table}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")";
        $this->query($sql, array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set  = array_map(function($k) { return "`{$k}` = ?"; }, array_keys($data));
        $sql  = "UPDATE `{$table}` SET " . implode(', ', $set) . " WHERE {$where}";
        $stmt = $this->query($sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query("DELETE FROM `{$table}` WHERE {$where}", $params)->rowCount();
    }

    /** @return mixed */
    public function transaction(callable $cb)
    {
        $this->pdo->beginTransaction();
        try {
            $result = $cb($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        try {
            $this->query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function escapeLike(string $v): string
    {
        return str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $v);
    }
}

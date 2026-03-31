<?php
/**
 * app/Models/BaseModel.php
 * Base class untuk semua Model.
 *
 * Tim: Database
 *
 * Model bertanggung jawab HANYA untuk:
 *   - Query SQL ke database
 *   - Validasi data sebelum INSERT/UPDATE
 *   - Relasi antar tabel
 *
 * Model TIDAK boleh:
 *   - Mengakses $_GET, $_POST, $_SESSION
 *   - Memanggil redirect() atau header()
 *   - Me-render HTML
 */

abstract class BaseModel
{
    protected static string $table = '';

    protected static function db(): PDO
    {
        return getDB();
    }

    /**
     * Eksekusi query dan return semua baris
     */
    protected static function query(string $sql, array $params = []): array
    {
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Eksekusi query dan return satu baris
     */
    protected static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Eksekusi query tanpa return data (INSERT/UPDATE/DELETE)
     */
    protected static function execute(string $sql, array $params = []): int
    {
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Dapatkan ID terakhir yang diinsert
     */
    protected static function lastId(): int
    {
        return (int) static::db()->lastInsertId();
    }

    /**
     * Hitung baris
     */
    protected static function count(string $sql, array $params = []): int
    {
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Cari satu baris berdasarkan primary key
     */
    public static function find(int $id): ?array
    {
        if (!static::$table) return null;
        return static::queryOne("SELECT * FROM " . static::$table . " WHERE id = ? LIMIT 1", [$id]);
    }

    /**
     * Helper pagination
     */
    protected static function buildPagination(int $total, int $page, int $perPage = PER_PAGE): array
    {
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = max(1, min($page, $totalPages));
        return [
            'total'       => $total,
            'per_page'    => $perPage,
            'current'     => $page,
            'total_pages' => $totalPages,
            'offset'      => ($page - 1) * $perPage,
        ];
    }
}

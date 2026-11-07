<?php

declare(strict_types=1);

namespace StoreLift;

use PDO;

final class OrderStore
{
    private PDO $db;

    public function __construct(string $path)
    {
        $this->db = new PDO('sqlite:' . $path, options: [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->db->exec('PRAGMA busy_timeout = 5000');
        $this->db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS orders (
                attempt_id TEXT PRIMARY KEY,
                owner TEXT NOT NULL,
                paypal_id TEXT UNIQUE,
                capture_id TEXT UNIQUE,
                sku TEXT NOT NULL,
                revision TEXT NOT NULL,
                cents INTEGER NOT NULL,
                currency TEXT NOT NULL,
                merchant_id TEXT NOT NULL,
                asset_hash TEXT NOT NULL,
                state TEXT NOT NULL CHECK (state IN ('creating', 'created', 'pending', 'paid', 'revoked')),
                created_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS orders_owner ON orders(owner, created_at);
            CREATE TABLE IF NOT EXISTS webhook_events (id TEXT PRIMARY KEY, received_at INTEGER NOT NULL);
            CREATE TABLE IF NOT EXISTS revoked_captures (id TEXT PRIMARY KEY);
            SQL);
    }

    public function startAttempt(string $id, string $owner, string $merchant, string $assetHash): array
    {
        $this->run(<<<'SQL'
            INSERT INTO orders (attempt_id, owner, sku, revision, cents, currency, merchant_id, asset_hash, state, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'creating', ?) ON CONFLICT(attempt_id) DO NOTHING
            SQL, [$id, $owner, Catalog::SKU, Catalog::REVISION, Catalog::CENTS, Catalog::CURRENCY, $merchant, $assetHash, time()]);
        return $this->one('SELECT * FROM orders WHERE attempt_id = ? AND owner = ?', [$id, $owner])
            ?? throw new HttpError(404, 'order_not_found', 'This checkout is unavailable in this session.');
    }

    public function linkOrder(string $id, string $paypalId, string $merchant): void
    {
        $written = $this->run(<<<'SQL'
            UPDATE orders SET paypal_id = ?, merchant_id = ?, state = 'created'
            WHERE attempt_id = ? AND paypal_id IS NULL AND (merchant_id = '' OR merchant_id = ?)
            SQL, [$paypalId, $merchant, $id, $merchant])->rowCount();
        if ($written === 0 && $this->one(
            'SELECT 1 FROM orders WHERE attempt_id = ? AND paypal_id = ? AND merchant_id = ?', [$id, $paypalId, $merchant]
        ) === null) {
            throw new HttpError(409, 'order_mismatch', 'This checkout could not be linked to its PayPal order.');
        }
    }

    public function owned(string $paypalId, string $owner): array
    {
        return $this->one('SELECT * FROM orders WHERE paypal_id = ? AND owner = ?', [$paypalId, $owner])
            ?? throw new HttpError(404, 'order_not_found', 'This checkout is unavailable in this session.');
    }

    public function latest(string $owner): ?array
    {
        return $this->one(<<<'SQL'
            SELECT * FROM orders WHERE owner = ? AND paypal_id IS NOT NULL
            ORDER BY created_at DESC, rowid DESC LIMIT 1
            SQL, [$owner]);
    }

    public function webhookOrder(?string $paypalId, string $captureId): ?array
    {
        if ($paypalId !== null) {
            return $this->one('SELECT * FROM orders WHERE paypal_id = ?', [$paypalId]);
        }
        return $this->one('SELECT * FROM orders WHERE capture_id = ?', [$captureId]);
    }

    public function recordCapture(string $paypalId, string $captureId, string $state): array
    {
        $written = $this->run(<<<'SQL'
            UPDATE orders SET capture_id = ?, state = CASE
                WHEN state = 'revoked' OR ? = 'revoked' OR EXISTS (SELECT 1 FROM revoked_captures WHERE id = ?) THEN 'revoked'
                WHEN state = 'paid' THEN 'paid'
                ELSE ? END
            WHERE paypal_id = ? AND (capture_id IS NULL OR capture_id = ?)
            SQL, [$captureId, $state, $captureId, $state, $paypalId, $captureId])->rowCount();
        if ($written === 0) {
            throw new HttpError(409, 'capture_mismatch', 'PayPal returned a different capture for this order.');
        }
        return $this->one('SELECT * FROM orders WHERE paypal_id = ?', [$paypalId]);
    }

    public function eventSeen(string $id): bool
    {
        return $this->one('SELECT 1 FROM webhook_events WHERE id = ?', [$id]) !== null;
    }

    public function markEvent(string $id): void
    {
        $this->run('INSERT INTO webhook_events VALUES (?, ?) ON CONFLICT(id) DO NOTHING', [$id, time()]);
    }

    public function revoke(string $captureId, string $eventId): void
    {
        $this->db->beginTransaction();
        try {
            $this->run('INSERT INTO revoked_captures VALUES (?) ON CONFLICT(id) DO NOTHING', [$captureId]);
            $this->run("UPDATE orders SET state = 'revoked' WHERE capture_id = ?", [$captureId]);
            $this->markEvent($eventId);
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    private function run(string $sql, array $values): \PDOStatement
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($values);
        return $statement;
    }

    private function one(string $sql, array $values): ?array
    {
        return $this->run($sql, $values)->fetch() ?: null;
    }
}

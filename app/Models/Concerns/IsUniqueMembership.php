<?php

namespace App\Models\Concerns;

use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A membership row that is unique per (user_id, parent key), enforced by the UNA-004 unique
 * indexes. Use findOrCreateMembership() for every insert instead of exists() + create().
 */
trait IsUniqueMembership
{
    /** The parent column of the (user_id, parent) unique pair, e.g. 'pod_id'. */
    abstract public static function membershipParentKey(): string;

    /**
     * Atomically return the single membership of $userId in $parentId, creating it with
     * $values only if none exists. An existing row is returned untouched (never reactivated,
     * reset or downgraded). Safe under concurrent requests: a losing insert hits the unique
     * index and the winner's row is returned instead.
     *
     * Run side effects (notifications, emails, default grants) only when
     * $membership->wasRecentlyCreated is true.
     */
    public static function findOrCreateMembership(int $userId, int $parentId, array $values = []): static
    {
        $existing = static::query()->where(static::membershipKey($userId, $parentId))->first();

        return $existing ?? static::createMembershipOrReturnExisting($userId, $parentId, $values);
    }

    /**
     * Insert first; on a unique-key collision (only that error) return the row that won.
     * The re-read is a locking read so it sees a concurrently committed row even inside a
     * REPEATABLE READ transaction that has already taken its snapshot.
     */
    public static function createMembershipOrReturnExisting(int $userId, int $parentId, array $values = []): static
    {
        $key = static::membershipKey($userId, $parentId);
        $query = static::query();

        try {
            return $query->withSavepointIfNeeded(fn () => static::query()->create($key + $values));
        } catch (UniqueConstraintViolationException $e) {
            return static::query()->useWritePdo()->where($key)->sharedLock()->first() ?? throw $e;
        }
    }

    private static function membershipKey(int $userId, int $parentId): array
    {
        return ['user_id' => $userId, static::membershipParentKey() => $parentId];
    }
}

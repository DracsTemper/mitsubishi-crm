<?php

namespace Database\Seeders;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Shared create-only fixture checks; never updates an existing row. */
final class DevelopmentDemoFixtures
{
    public static function guard(): void
    {
        DevelopmentDemoSeeder::assertTarget();
    }

    public static function ensure(string $class, array $identity, array $attributes): Model
    {
        self::guard();
        $matches = self::identityQuery($class, $identity)->limit(2)->get();
        if ($matches->count() > 1) {
            throw new LogicException('Ambiguous demo identity: '.$class.' '.json_encode($identity));
        }
        if ($matches->isEmpty()) {
            return $class::query()->create([...$attributes, ...$identity]);
        }

        $existing = $matches->first();
        $expected = new $class;
        $expected->forceFill([...$attributes, ...$identity]);
        foreach (array_keys([...$attributes, ...$identity]) as $field) {
            // Passwords are creation defaults, never reset or compared on repeat runs.
            if ($field === 'password') {
                continue;
            }
            if (self::value($existing->getAttribute($field)) !== self::value($expected->getAttribute($field))) {
                throw new LogicException('Conflicting demo record: '.$class.' '.json_encode($identity).' field '.$field.'. Existing data was not changed.');
            }
        }

        return $existing;
    }

    public static function require(string $class, array $identity): Model
    {
        self::guard();
        $matches = self::identityQuery($class, $identity)->limit(2)->get();
        if ($matches->count() !== 1) {
            throw new LogicException('Missing or ambiguous demo prerequisite: '.$class.' '.json_encode($identity).'. Run DevelopmentDemoSeeder.');
        }

        return $matches->first();
    }

    private static function identityQuery(string $class, array $identity): \Illuminate\Database\Eloquent\Builder
    {
        $model = new $class;
        $query = $class::query();
        foreach ($identity as $field => $value) {
            // SQLite stores cast dates with midnight, whereas MySQL DATE omits it.
            if (($model->getCasts()[$field] ?? null) === 'date') {
                $query->whereDate($field, $value);
            } else {
                $query->where($field, $value);
            }
        }
        return $query;
    }

    private static function value(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => (string) $value,
        };
    }
}

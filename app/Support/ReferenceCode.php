<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReferenceCode
{
    public static function unique(string $source, Builder $query, ?Model $ignore = null, string $column = 'code', int $maxLength = 50): string
    {
        $base = Str::of($source)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->limit(max(3, $maxLength - 4), '')
            ->toString();

        $base = $base !== '' ? $base : 'REF';
        $code = $base;
        $counter = 2;

        while (self::exists($query, $column, $code, $ignore)) {
            $suffix = '-'.$counter++;
            $code = Str::limit($base, $maxLength - strlen($suffix), '').$suffix;
        }

        return $code;
    }

    private static function exists(Builder $query, string $column, string $code, ?Model $ignore): bool
    {
        $candidate = clone $query;

        return $candidate
            ->where($column, $code)
            ->when($ignore, fn (Builder $scope) => $scope->whereKeyNot($ignore->getKey()))
            ->exists();
    }
}

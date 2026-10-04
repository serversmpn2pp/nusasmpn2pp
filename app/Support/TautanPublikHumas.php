<?php

namespace App\Support;

class TautanPublikHumas
{
    public const KREDENSIAL = ['password', 'kata_sandi', 'token', 'token_akses', 'access_token', 'refresh_token', 'api_key', 'secret', 'client_secret', 'authorization', 'auth_token', 'id_token'];

    public static function berkredensial(string $tautan): bool
    {
        $url = parse_url($tautan);
        if (! $url) {
            return false;
        }
        if (isset($url['user']) || isset($url['pass'])) {
            return true;
        }
        parse_str($url['query'] ?? '', $query);
        parse_str($url['fragment'] ?? '', $fragment);
        $keys = array_merge(array_keys($query), array_keys($fragment));
        foreach ([$query, $fragment] as $parameters) {
            array_walk_recursive($parameters, function ($value, $key) use (&$keys) {
                $keys[] = (string) $key;
            });
        }

        return (bool) array_intersect(array_map(fn ($key) => strtolower((string) $key), $keys), self::KREDENSIAL);
    }

    public static function hash(?string $tautan): ?string
    {
        if (! $tautan) {
            return null;
        }
        $url = parse_url($tautan);
        $alamat = strtolower($url['scheme']).'://'.strtolower($url['host']).(isset($url['port']) ? ':'.$url['port'] : '').rtrim($url['path'] ?? '', '/');
        if (isset($url['query'])) {
            $alamat .= '?'.$url['query'];
        }

        return hash('sha256', $alamat);
    }
}

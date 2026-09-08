<?php

namespace App\Support;

class StorefrontUrl
{
    private const ENGLISH_ROUTES = [
        'products.index' => 'en.products.index',
        'products.show' => 'en.products.show',
        'products.quick-review' => 'en.products.quick-review',
    ];

    public static function route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        if (app()->getLocale() === 'en' && isset(self::ENGLISH_ROUTES[$name])) {
            $name = self::ENGLISH_ROUTES[$name];
        }

        return route($name, $parameters, $absolute);
    }

    /** @return array{ka: string, en: string, x_default: string} */
    public static function alternates(): array
    {
        $route = request()->route();
        $name = $route?->getName();
        $parameters = $route ? $route->parameters() : [];
        $query = request()->query();
        $kaName = array_search($name, self::ENGLISH_ROUTES, true) ?: $name;
        $enName = self::ENGLISH_ROUTES[$kaName] ?? $name;
        $ka = $kaName ? route($kaName, $parameters) : url()->current();
        $en = $enName ? route($enName, $parameters) : url()->current();

        if ($query !== []) {
            $ka .= '?' . http_build_query($query);
            $en .= '?' . http_build_query($query);
        }

        return ['ka' => $ka, 'en' => $en, 'x_default' => $ka];
    }

    public static function switchLocaleUrl(string $locale, ?string $referer = null): string
    {
        $path = '/';
        $query = [];

        if ($referer) {
            $parts = parse_url($referer);
            if ($parts !== false && (! isset($parts['host']) || $parts['host'] === request()->getHost())) {
                $path = $parts['path'] ?? '/';
                if (isset($parts['query'])) {
                    parse_str($parts['query'], $query);
                }
            }
        }

        $path = '/' . ltrim($path, '/');
        if ($locale === 'en' && preg_match('#^/products(?:/|$)#', $path)) {
            $path = '/en' . $path;
        }
        if ($locale === 'ka' && preg_match('#^/en/products(?:/|$)#', $path)) {
            $path = substr($path, 3);
        }

        return url($path) . ($query === [] ? '' : '?' . http_build_query($query));
    }
}

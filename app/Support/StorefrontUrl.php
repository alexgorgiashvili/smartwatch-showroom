<?php

namespace App\Support;

class StorefrontUrl
{
    private const ENGLISH_ROUTES = [
        'home' => 'en.home',
        'products.index' => 'en.products.index',
        'products.show' => 'en.products.show',
        'products.quick-review' => 'en.products.quick-review',
        'contact' => 'en.contact', 'faq' => 'en.faq', 'about' => 'en.about',
        'privacy' => 'en.privacy', 'terms' => 'en.terms',
        'blog.index' => 'en.blog.index', 'blog.show' => 'en.blog.show',
        'landing.age' => 'en.landing.age', 'landing.sim-guide' => 'en.landing.sim-guide',
        'landing.gift-guide' => 'en.landing.gift-guide', 'landing.city' => 'en.landing.city',
        'gift-builder.show' => 'en.gift-builder.show', 'gift-builder.boxes' => 'en.gift-builder.boxes',
        'gift-boxes.options' => 'en.gift-boxes.options',
        'cart.index' => 'en.cart.index', 'checkout.index' => 'en.checkout.index',
        'payment.success' => 'en.payment.success', 'payment.fail' => 'en.payment.fail',
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
        if ($locale === 'en' && ! str_starts_with($path, '/en')) {
            $path = '/en' . $path;
        }
        if ($locale === 'ka' && ($path === '/en' || str_starts_with($path, '/en/'))) {
            $path = substr($path, 3);
        }

        return url($path) . ($query === [] ? '' : '?' . http_build_query($query));
    }
}

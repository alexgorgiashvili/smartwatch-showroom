<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Breadcrumbs extends Component
{
    public array $items;

    /**
     * Create a new component instance.
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Generate JSON-LD schema for breadcrumbs
     */
    public function schema(): string
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => []
        ];

        foreach ($this->items as $position => $item) {
            $element = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $item['name'],
            ];

            if (isset($item['url'])) {
                $element['item'] = $item['url'];
            }

            $schema['itemListElement'][] = $element;
        }

        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.breadcrumbs');
    }
}

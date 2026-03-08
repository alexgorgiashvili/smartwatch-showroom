<?php

namespace App\Services\Chatbot;

class CarouselBuilderService
{
    /**
     * @param array<int, array<string, mixed>> $matches
     * @return array<int, array<string, string>>
     */
    public function productsFromMatches(array $matches, int $minScore = 2): array
    {
        $cards = [];

        foreach ($matches as $match) {
            $metadata = is_array($match['metadata'] ?? null) ? $match['metadata'] : [];
            $slug = (string) ($metadata['slug'] ?? '');
            $title = (string) ($metadata['title'] ?? $metadata['name'] ?? 'Smartwatch');
            $imageUrl = (string) ($metadata['image_url'] ?? '');
            $price = $this->resolvePriceLabel($metadata);
            $score = (float) ($match['score'] ?? 0.0);

            if ($slug === '' || $imageUrl === '' || $score < 0.5) {
                continue;
            }

            $cards[] = [
                'title' => mb_substr($title, 0, 80),
                'subtitle' => mb_substr($price, 0, 160),
                'image_url' => $imageUrl,
                'product_url' => url('/products/' . $slug),
                'cta_label' => 'ნახვა',
                'cart_label' => 'კალათაში',
            ];

            if (count($cards) >= 10) {
                break;
            }
        }

        if (count($cards) < $minScore) {
            return [];
        }

        return $cards;
    }

    /**
     * @param array<int, array<string, string>> $products
     */
    public function buildWhatsAppCarousel(array $products): array
    {
        $products = array_values(array_slice($products, 0, 10));

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'product_list',
                'header' => [
                    'type' => 'text',
                    'text' => 'შერჩეული მოდელები',
                ],
                'body' => [
                    'text' => 'თქვენს მოთხოვნაზე შესაბამისი ვარიანტები 👇',
                ],
                'action' => [
                    'catalog_id' => (string) config('services.whatsapp.business_id', ''),
                    'sections' => [
                        [
                            'title' => 'რეკომენდაციები',
                            'product_items' => array_values(array_map(static function (array $product): array {
                                return [
                                    'product_retailer_id' => md5(($product['title'] ?? '') . '|' . ($product['product_url'] ?? '')),
                                ];
                            }, $products)),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, string>> $products
     */
    public function buildInstagramCarousel(array $products): array
    {
        return $this->buildMetaGenericCarousel($products);
    }

    /**
     * @param array<int, array<string, string>> $products
     */
    public function buildMetaGenericCarousel(array $products): array
    {
        $elements = [];

        foreach (array_slice($products, 0, 10) as $product) {
            $elements[] = [
                'title' => mb_substr((string) ($product['title'] ?? 'Smartwatch'), 0, 80),
                'image_url' => (string) ($product['image_url'] ?? ''),
                'subtitle' => mb_substr((string) ($product['subtitle'] ?? ''), 0, 80),
                'default_action' => [
                    'type' => 'web_url',
                    'url' => (string) ($product['product_url'] ?? url('/products')),
                ],
                'buttons' => [
                    [
                        'type' => 'web_url',
                        'url' => (string) ($product['product_url'] ?? url('/products')),
                        'title' => mb_substr((string) ($product['cta_label'] ?? 'ნახვა'), 0, 20),
                    ],
                    [
                        'type' => 'web_url',
                        'url' => url('/cart'),
                        'title' => mb_substr((string) ($product['cart_label'] ?? 'კალათაში'), 0, 20),
                    ],
                ],
            ];
        }

        return [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'generic',
                    'elements' => $elements,
                ],
            ],
        ];
    }

    private function resolvePriceLabel(array $metadata): string
    {
        $salePrice = is_numeric($metadata['sale_price'] ?? null) ? (float) $metadata['sale_price'] : null;
        $price = is_numeric($metadata['price'] ?? null) ? (float) $metadata['price'] : null;

        if ($salePrice !== null && $price !== null && $salePrice < $price) {
            return 'ფასი: ' . $salePrice . ' ₾ (ძველი: ' . $price . ' ₾)';
        }

        if ($salePrice !== null) {
            return 'ფასი: ' . $salePrice . ' ₾';
        }

        if ($price !== null) {
            return 'ფასი: ' . $price . ' ₾';
        }

        return 'ფასი მოთხოვნით';
    }
}

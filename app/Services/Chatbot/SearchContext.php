<?php

namespace App\Services\Chatbot;

use App\Models\Product;
use Illuminate\Support\Collection;

class SearchContext
{
    public function __construct(
        private string $ragContext,
        private Collection $products,
        private ?Product $requestedProduct,
        private ?string $productNotFoundMessage
    ) {
    }

    public function ragContext(): string
    {
        return $this->ragContext;
    }

    public function products(): Collection
    {
        return $this->products;
    }

    public function requestedProduct(): ?Product
    {
        return $this->requestedProduct;
    }

    public function productNotFoundMessage(): ?string
    {
        return $this->productNotFoundMessage;
    }
}

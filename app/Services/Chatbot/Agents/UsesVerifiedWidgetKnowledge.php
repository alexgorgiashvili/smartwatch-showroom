<?php

namespace App\Services\Chatbot\Agents;

use App\Services\Chatbot\IntentResult;
use App\Services\Chatbot\SearchContext;

trait UsesVerifiedWidgetKnowledge
{
    private function requestedProductSlugForValidation(string $message, IntentResult $intent, ?SearchContext $search): ?string
    {
        $product = $search?->requestedProduct();
        if (!$product) {
            return null;
        }

        $question = mb_strtolower($message);
        $name = mb_strtolower(trim((string) $product->name));
        $slug = mb_strtolower(trim((string) $product->slug));
        if (!$intent->hasSpecificProduct()
            && !($name !== '' && str_contains($question, $name))
            && !($slug !== '' && str_contains($question, $slug))) {
            return null;
        }

        return $slug !== '' ? $slug : null;
    }

    private function withWidgetValidationGuard(array $context, array $runtime): array
    {
        if (($runtime['channel'] ?? null) === 'widget' && ($runtime['cohort'] ?? null) === 'v2') {
            $context['require_live_catalog_evidence'] = true;
        }

        return $context;
    }

    private function withVerifiedWidgetKnowledge(string $systemPrompt, array $runtime): string
    {
        $isWidgetV2 = ($runtime['channel'] ?? null) === 'widget' && ($runtime['cohort'] ?? null) === 'v2';
        if (!$isWidgetV2 && ($runtime['channel'] ?? null) !== 'evaluation') {
            return $systemPrompt;
        }

        if ($isWidgetV2) {
            $systemPrompt .= "\n\nWIDGET EVIDENCE RULES:\n"
                . 'For SIM, GPS, video calling, water resistance, battery life, and setup app questions, state a capability only when the provided product context explicitly verifies it. '
                . 'If no model or verified specification is available, ask for the model and say that the detail still needs confirmation; do not infer features from product type. '
                . 'Do not promise “ზუსტად გეტყვით” or “დაგიზუსტებთ” merely because the customer will provide a model name; the actual specification or support must be checked first. '
                . 'The widget accepts text only, so ask for a model name or product link, never a photo or attachment. '
                . 'If no matching products were passed in context, do not claim the whole store catalog is empty or broken; a broad product search may have missed it. '
                . 'If a customer refers to "this" device or asks a follow-up without a named model and no conversation history identifies it, do not select an arbitrary catalog model; ask which model they mean. '
                . 'Treat water-resistance text as a catalog claim only; do not infer that a device is safe for swimming or immersion. '
                . 'For ordering questions, explain that the customer can choose a product, add it to the cart, and finish checkout on the website; this text chat cannot submit an order. '
                . 'A quoted price alone does not identify which watch the customer means; ask for its model or link before comparing its functions with another watch. '
                . 'Answer every part of a combined question, including delivery and contact details when both are asked. '
                . 'Use Georgian script for the answer. Do not mix in Armenian words or Armenian punctuation marks. '
                . 'If the customer asks to return, exchange, or have the shop take back a watch, explain the verified conditional 14-day model-exchange policy when it appears in the provided knowledge. Distinguish model exchange from a cash refund; never promise a cash refund without a verified refund policy.';
        }

        $knowledge = trim((string) ($runtime['knowledge_context'] ?? ''));
        if ($knowledge === '') {
            return $systemPrompt;
        }

        return $systemPrompt . "\n\nVERIFIED WIDGET BUSINESS POLICY:\n"
            . 'For prices and stock, use the live SQL product context as the authority. '
            . 'For delivery, payment, warranty, and exchange policies, the verified facts below override conflicting legacy RAG text. '
            . 'Do not apply a general policy to a product capability without verified product evidence.'
            . "\n" . $knowledge;
    }
}

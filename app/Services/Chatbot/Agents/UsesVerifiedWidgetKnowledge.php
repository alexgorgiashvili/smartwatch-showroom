<?php

namespace App\Services\Chatbot\Agents;

trait UsesVerifiedWidgetKnowledge
{
    private function withWidgetValidationGuard(array $context, array $runtime, string $intent): array
    {
        if (($runtime['channel'] ?? null) === 'widget' && ($runtime['cohort'] ?? null) === 'v2') {
            $context['require_live_catalog_evidence'] = true;
            $context['catalog_intent'] = $intent;
        }

        return $context;
    }

    private function withVerifiedWidgetKnowledge(string $systemPrompt, array $runtime): string
    {
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

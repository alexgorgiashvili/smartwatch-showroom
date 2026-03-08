# Next Steps

This file captures the current continuation point after the chatbot hardening closure and the lightweight documentation cleanup.

## Current status

- `CHATBOT_SYSTEM_HARDENING_PLAN.md` is effectively closed for the completed hardening phases.
- The temporary standalone planning docs were removed during cleanup to avoid leaving extra one-off Markdown files in the repo.
- The omnichannel parity follow-up is now implemented and verified; the widget remains the reference behavior, but the previously tracked parity gaps in this file are closed.

## Outcome

Omnichannel behavior is now aligned with the hardened widget runtime on the previously tracked parity items.

## Progress update

- Done: omnichannel now uses the shared fallback-resolution contract instead of fully inline fallback handling.
- Done: validator failure in omnichannel now attempts one regeneration pass before falling back.
- Done: fallback reason taxonomy is partially normalized, including `generic_repeated` and persisted reply metadata for text and discovery-carousel auto-replies.
- Done: WhatsApp and Meta carousel payload construction now flows through `CarouselBuilderService` instead of being duplicated per channel.
- Done: widget and omnichannel now share the same product selection/suppression contract through `ChatbotProductSelectionService`.
- Done: omnichannel discovery carousel now suppresses product-specific recommendation queries instead of sending ambiguous cards.
- Done: parity-focused coverage now covers shared carousel payload contracts, fallback/regeneration metadata, discovery carousel metadata, and discovery suppression fallback.

### P0

1. Introduce a shared pipeline entry point for widget and omnichannel flows, or extract a shared runtime contract both paths use. `In progress`
1. Introduce a shared pipeline entry point for widget and omnichannel flows, or extract a shared runtime contract both paths use. `Done via shared fallback-resolution, metadata, and product-selection/suppression contracts`
2. Add validator-regeneration parity to omnichannel so validation failure does not fall straight to a hard integrity fallback. `Done`
3. Normalize fallback metadata and reason codes across widget and omnichannel responses. `Done for current auto-reply paths`

### P1

1. Align omnichannel discovery carousel and product-selection behavior with the widget's stronger ambiguity and suppression rules. `Done`
2. Reduce duplication by centralizing carousel payload building behind `CarouselBuilderService`. `Done`
3. Expand omnichannel tests to assert parity behavior, not just webhook plumbing and basic fallback cases. `Done`

## Verification snapshot

- `tests/Feature/ChatbotWidgetProductSuppressionTest.php`
- `tests/Unit/OmnichannelServiceTest.php`
- `php artisan test --filter=AiConversationServiceFallbackTest`

## Follow-up note

No additional parity work is queued in this file. Future work, if needed, should be treated as a new enhancement pass rather than as unfinished parity cleanup.

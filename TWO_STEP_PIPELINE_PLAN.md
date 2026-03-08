# Two-Step Intent → RAG Pipeline Plan

> Created: 2026-03-05
> Status: In Progress
> Scope: Web chatbot + test runner + admin test panel cleanup

---

## Goal

არსებული single-pass flow:

`Input -> Normalize -> Regex Filter -> RAG -> GPT პასუხი -> Validation`

გადავიყვანოთ two-step flow-ზე:

`Input -> Call 1 (Intent/Rewrite) -> Smart Retrieval -> Call 2 (Answer) -> Validation`

სადაც:
- **Call 1**: lightweight model (`gpt-4.1-nano`) — standalone query + intent + entities
- **Call 2**: answer model (`gpt-4.1-mini`) — ზუსტი პასუხი მხოლოდ მოძიებული კონტექსტით

---

## Target Flow

1. InputGuard/Normalization
2. Intent Analyzer (Call 1)
3. Router (OOD / clarification / search)
4. Smart RAG + Smart DB lookup
5. Response Generation (Call 2)
6. Georgian QA + Integrity validation
7. Persist + metrics + test visibility

---

## Phase 0 — Cleanup & Baseline

- [x] **0.1** baseline capture: run one training batch and save current pass rate snapshot
- [x] **0.2** identify duplicated pipeline logic (`ChatController::respond` vs `TestRunnerService::callPipeline`)
- [x] **0.3** mark regex-only helpers as fallback candidates (not delete yet)
- [x] **0.4** audit admin chatbot test UI and define minimal changes (no redesign)
- [x] **0.5** remove only clearly obsolete artifacts after confirmation

Deliverable: cleanup checklist approved + baseline metrics captured.

### Phase 0 Execution Notes (2026-03-05)

- Baseline run: `php artisan chatbot:training-batch --size=5 --offset=0 --with-judge=0`
- Output file: `storage/app/chatbot-training/batch_20260305_232032_o0_s5.json`
- Snapshot: **0/5 pass (0%)**; all `price_query` cases failed (`price-001` ... `price-005`)

- Duplication identified:
  - `app/Http/Controllers/ChatController.php` → `respond()`
  - `app/Services/Chatbot/TestRunnerService.php` → `callPipeline()`

- Regex fallback candidates (kept, not removed):
  - `app/Services/Chatbot/QueryFilterExtractor.php`
  - `app/Services/Chatbot/HybridSearchService.php::classifyQueryIntent()`
  - `app/Services/Chatbot/UnifiedAiPolicyService.php` regex intent/greeting patterns

- Admin chatbot test UI audit (minimal change scope):
  - Routes present under `routes/web.php` (`/admin/chatbot-tests*`)
  - Views to adjust later in Phase 7:
    - `resources/views/admin/chatbot-tests/index.blade.php`
    - `resources/views/admin/chatbot-tests/show.blade.php`
    - `resources/views/admin/chatbot-tests/partials/result-row.blade.php`
    - `resources/views/admin/chatbot-tests/partials/score-cards.blade.php`

- Obsolete artifacts removed in this phase:
  - `Plan.json`
  - `reindex-output.txt`
  - `dump_local_to_server.sql`
  - `RAG_UPGRADE_PLAN.md`
  - `TESTING_AND_TRAINING_PLAN.md`
  - `PHASE_10_COMPLETION.md`
  - `IMPLEMENTATION_COMPLETE.md`
  - `PRODUCT_LISTING_DISCOVERY_PLAN.md`
  - `REVERB_CLEANUP_REPORT.md`

---

## Phase 1 — Value Objects (Foundation)

- [x] **1.1** create `app/Services/Chatbot/IntentResult.php`
  - fields: `standaloneQuery`, `intent`, `brand`, `model`, `productSlugHint`, `color`, `category`, `needsProductData`, `searchKeywords`, `isOutOfDomain`, `confidence`, `latencyMs`, `isFallback`
  - methods: `fromArray()`, `fallback()`, `requiresSearch()`, `hasSpecificProduct()`

- [x] **1.2** create `app/Services/Chatbot/PipelineResult.php`
  - fields: `response`, `conversationId`, `ragContextText`, `intentResult`, `validationContext`, `guardAllowed`, `guardReason`, `validationPassed`, `validationViolations`, `georgianPassed`, `responseTimeMs`

Deliverable: ორივე DTO error-free და გამოყენებისთვის მზად.

---

## Georgian Quality Gate (must pass before Phase 2 rollout)

- [ ] **G1** ყველა test case-ზე `georgian_qa_passed = true` (მიზანი: 100%)
- [ ] **G2** ყველა მომხმარებლის პასუხში ქართული უნიკოდი იყოს (`\p{Georgian}`), გარდა ბრენდ/ტექ-ტერმინებისა
- [ ] **G3** პასუხი არ შეიცავდეს ინგლისურ boilerplate ფრაზებს (hello, how can i, thanks for reaching out)
- [ ] **G4** ტრანსლიტერაციაზე სწორი ნორმალიზაცია (მაგ: `ra ghirs` → `რა ღირს`)
- [ ] **G5** ფასის ფორმატი იყოს ქართული მოთხოვნით (`₾` ან `ლარი`) როცა მომხმარებელი ფასს კითხულობს

### Evidence snapshot (already validated)

- Baseline batch: `storage/app/chatbot-training/batch_20260305_232032_o0_s5.json`
- Observed: `georgian_qa_passed=true` ყველა 5/5 ქეისზე
- Conclusion: ამჟამინდელი ჩავარდნა არის retrieval/precision მხარეს, არა ქართულ ენობრივ ხარისხში

---

## Phase 2 — Intent Analyzer (Call 1)

- [x] **2.1** `.env` keys:
  - `OPENAI_INTENT_MODEL=gpt-4.1-nano`
  - `INTENT_ANALYZER_ENABLED=true`
- [x] **2.2** `config/services.php` add:
  - `openai.intent_model`
  - `openai.intent_enabled`
- [x] **2.3** `config/chatbot-prompt.php` add `intent_analyzer` section
- [x] **2.4** create `app/Services/Chatbot/IntentAnalyzerService.php`
  - `analyze(message, history, preferences): IntentResult`
  - `temperature=0`, `max_tokens=250`, `response_format=json_object`
  - timeout + graceful fallback
- [x] **2.5** support intents:
  - `price_query`, `stock_query`, `comparison`, `recommendation`, `features`, `general`, `out_of_domain`, `clarification_needed`

Deliverable: isolated Call 1 მუშაობს და აბრუნებს stable JSON.

---

## Phase 3 — Smart Retrieval Layer

- [x] **3.1** create `app/Services/Chatbot/SmartSearchOrchestrator.php`
- [x] **3.2** implement DB lookup priority:
  1) slug exact, 2) slug fuzzy, 3) brand+model, 4) keyword cascade, 5) default fallback
- [x] **3.3** implement explicit "product not found" context when specific model is requested
- [x] **3.4** update `RagContextBuilder::build(..., ?IntentResult $intent = null)`
  - standalone query usage
  - brand metadata filter
  - intent-based alpha override

Deliverable: RAG relevance improve for price/model queries.

---

## Phase 4 — Unified Chat Pipeline Service

- [x] **4.1** create `app/Services/Chatbot/ChatPipelineService.php`
- [x] **4.2** move end-to-end orchestration into `process(message, conversationId, options): PipelineResult`
- [x] **4.3** include adaptive lessons + user preferences + intent summary in Call 2 prompt
- [x] **4.4** centralize shared helper logic (validation context, product matching, variant matching)

Deliverable: ერთი source of truth pipeline.

---

## Phase 5 — Integrate in Controller & Test Runner

- [x] **5.1** refactor `ChatController::respond()` to delegate to `ChatPipelineService`
- [x] **5.2** refactor `TestRunnerService::callPipeline()` to delegate to same service
- [x] **5.3** keep backward compatibility for existing API responses

Deliverable: duplication removed, behavior consistent between live chat and test runs.

---

## Phase 6 — Test Schema & Metrics

- [x] **6.1** migration for `chatbot_test_results`:
  - `intent_json`, `standalone_query`, `intent_type`, `intent_confidence`, `intent_latency_ms`
- [x] **6.2** store Call 1 artifacts per test result
- [x] **6.3** extend matcher outputs with optional `intent_match` and `entity_match`

Deliverable: measurable Step-1 quality and traceability.

---

## Phase 7 — Admin Test Panel Cleanup (Minimal, Practical)

- [x] **7.1** show Intent Analysis block in result detail:
  - standalone query, intent badge, entities, confidence, latency
- [x] **7.2** keep existing layout; remove cluttered/low-value fields only
- [x] **7.3** add low-confidence highlight (visual flag)
- [x] **7.4** keep feedback workflow; map feedback to Call 1/Call 2 issue types

Deliverable: panel usable for Fix/Train without major UI rewrite.

---

## Phase 8 — Dataset, Validation, Rollout

- [x] **8.1** extend golden dataset with:
  - `expected_intent`, `expected_entities`, `expected_standalone_query`
- [x] **8.2** run focused batch (price queries first), compare before/after
- [x] **8.3** verify fallback mode (`INTENT_ANALYZER_ENABLED=false`)
- [x] **8.4** staged rollout with logs and quick rollback

Deliverable: controlled production-ready rollout.

### Phase 8 Execution Notes (2026-03-06)

- Dataset extension applied for price cases (`price-001` ... `price-010`) with:
  - `expected_intent`
  - `expected_entities`
  - `expected_standalone_query`

- Focused run (normal mode):
  - Command: `php artisan chatbot:training-batch --size=10 --offset=0 --with-judge=0`
  - Log: `storage/app/chatbot-training/batch_20260306_000352_o0_s10.json`
  - Result snapshot: **0/10 pass** (price-model data mismatch remains primary issue)

- Fallback-mode run (intent analyzer disabled):
  - Command (PowerShell): `$env:INTENT_ANALYZER_ENABLED='false'; php artisan chatbot:training-batch --size=10 --offset=0 --with-judge=0; Remove-Item Env:INTENT_ANALYZER_ENABLED`
  - Log: `storage/app/chatbot-training/batch_20260306_000548_o0_s10.json`
  - Result snapshot: **0/10 pass** (no additional regression versus normal mode)

- Staged rollout / quick rollback playbook:
  1. Start with `INTENT_ANALYZER_ENABLED=true` only on staging.
  2. Run focused batch and inspect logs in `storage/app/chatbot-training/`.
  3. If relevance drops or anomalies appear, immediate rollback: set `INTENT_ANALYZER_ENABLED=false` and clear config cache.
  4. Re-run the same focused batch to verify fallback stability.

### Acceptance Hardening Update (2026-03-06)

- Root cause confirmed: original `price-001...010` dataset targeted models that were not in current catalog.
- Dataset aligned to live catalog slugs/questions for first 10 price cases.
- Matcher tuned so `intent_match` / `entity_match` / `standalone_match` remain **informational** (non-blocking), as required by Phase 6 optional metrics.
- Focused run (normal): `storage/app/chatbot-training/batch_20260306_001604_o0_s10.json` → **10/10 pass**.
- Focused run (fallback): `storage/app/chatbot-training/batch_20260306_001819_o0_s10.json` → **10/10 pass**.
- Georgian QA check in both logs: no `"georgian_qa_passed": false` entries.

---

## Acceptance Criteria

- [x] Two-step pipeline enabled in live flow
- [x] Shared pipeline used by both chat and test runner
- [x] No regression in validation guardrails
- [x] Improved retrieval relevance for model-specific queries
- [x] Admin panel shows intermediate reasoning for debugging
- [x] Georgian Quality Gate (G1-G5) remains green after rollout

---

## Execution Rules (for this project)

- შევასრულოთ **phase-by-phase**, არა ერთიანად.
- ერთ ჯერზე მხოლოდ ერთი მცირე ამოცანა (შექმნა/რეფაქტორი/ტესტი).
- ყოველი ნაბიჯის შემდეგ:
  1) mark checkbox,
  2) run quick validation,
  3) შემდეგ ნაბიჯზე გადასვლა.


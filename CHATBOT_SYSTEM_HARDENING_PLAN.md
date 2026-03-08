# Chatbot System Hardening Plan

## Baseline Assessment

Current overall score: **7/10**

Breakdown:

1. Runtime chatbot core: **6.5/10**
2. Admin testing panel / Chatbot Lab: **7.8/10**
3. Automated testing confidence: **5.8/10**

This is a good v1 system with real operator value, but it is not yet a top-tier production chatbot. The next phase should focus less on adding surface features and more on reliability, failure-mode handling, and end-to-end confidence.

## Main Goal

Raise the chatbot system from a solid v1 into a production-grade, easier-to-trust system by improving:

1. Runtime correctness
2. Fallback consistency
3. Failure recovery
4. Admin testing workflow quality
5. Automated confidence before changes ship

## Guiding Principles

1. Fix reliability before adding more feature depth.
2. Prefer fewer, clearer system behaviors over many fallback branches.
3. Make debugging actionable, not just visible.
4. Optimize the admin panel for daily tuning work, not for demo value.
5. Increase test confidence around real runtime paths, not only isolated helpers.

## Current Strengths Worth Preserving

1. Layered safety approach in the runtime pipeline.
2. Product-aware response generation and validator checks.
3. Clear admin separation between manual testing, training cases, and evaluation runs.
4. Existing debug visibility in Chatbot Lab.
5. Existing targeted tests around validator behavior, stock-aware prompt context, and Chatbot Lab workflow.

## Main Problems To Solve

### 1. Runtime Fallback Logic Is Too Fragmented

The chatbot currently has several independent fallback paths across guard handling, greeting handling, provider failure, strict Georgian QA, and validator rejection. This is safe, but it creates inconsistent user experience and lowers answer quality.

Impact:

1. Harder to reason about runtime behavior
2. Harder to debug why a bad answer happened
3. Too many cases end in generic fallback instead of targeted clarification or regeneration

### 2. Widget Message Persistence Is Not Transaction-Safe

The widget request flow creates the user message first, runs the pipeline, and only then creates the bot message. If the request fails mid-flight, the conversation can be left in a partially persisted state.

Impact:

1. Broken audit trail
2. Harder investigation of failures
3. Possible confusing admin inbox state

### 3. Validator Failure Handling Is Too Harsh

When validation fails, the runtime replaces the answer with a generic integrity fallback instead of trying one controlled regeneration pass using the validation context.

Impact:

1. Lower answer quality than necessary
2. Missed opportunity to self-correct price/stock mistakes
3. Higher fallback rate than needed

### 4. Admin Evaluation Runs Are Still Too Synchronous

The Chatbot Lab run flow currently executes selected cases in request lifecycle. This is acceptable for small runs, but becomes operationally weak as the suite grows.

Impact:

1. Slow response for admins
2. Timeout risk
3. No cancellation or background progress control

### 5. Manual Testing Is Useful But Not Yet Ideal

The current manual test mode is good for quick reproduction, but it creates disposable lab conversations and does not yet support a stronger persistent-session workflow for real debugging of multi-turn drift.

Impact:

1. Good for snapshots
2. Less good for realistic conversation replay
3. Harder to inspect long-turn degradation

### 6. Test Coverage Still Undershoots Production Confidence

There is valuable coverage already, but not enough around true end-to-end pipeline behavior, failure paths, conversation memory, and degraded external service conditions.

Impact:

1. Refactors remain riskier than they should be
2. Failure-mode confidence is weak
3. Admin confidence can be misleading if the real runtime path is not deeply tested

## Scope Of This Plan

This plan is divided into three priority levels:

1. Blockers
2. Important improvements
3. Nice-to-have improvements

Implementation should follow that order.

## Blockers

These should be treated as the highest-priority work before expanding the system further.

### Blocker 1: Centralize Fallback Decision Logic

Objective:

Create one clear decision layer for runtime failure handling so the system chooses between:

1. Normal response
2. Clarification request
3. Regeneration attempt
4. Safe fallback

Suggested implementation:

1. Introduce a dedicated fallback strategy component used by the runtime pipeline.
2. Normalize all fallback triggers into structured reason codes.
3. Ensure guard, strict Georgian, provider failure, empty output, and validator failure all pass through one decision path.
4. Record the chosen fallback strategy in debug output and metrics.

Likely files affected:

1. `app/Services/Chatbot/ChatPipelineService.php`
2. `app/Services/Chatbot/UnifiedAiPolicyService.php`
3. `app/Services/Chatbot/ResponseValidatorService.php`
4. `app/Services/Chatbot/ChatbotLabService.php`

Acceptance criteria:

1. All non-happy-path outcomes map to explicit reason codes.
2. Debug output shows one canonical fallback reason.
3. Fallback behavior is consistent across widget and admin manual tests.

### Blocker 2: Add One Controlled Regeneration Pass On Validation Failure

Objective:

Before returning the generic integrity fallback, give the system one chance to regenerate the answer with explicit validation constraints.

Suggested implementation:

1. On validator failure, build a regeneration prompt that lists violations.
2. Retry once only.
3. Re-run validation on the regenerated answer.
4. Use integrity fallback only if the second pass still fails.

Likely files affected:

1. `app/Services/Chatbot/ChatPipelineService.php`
2. `app/Services/Chatbot/ResponseValidatorService.php`

Acceptance criteria:

1. Price/stock/url mismatch responses can self-correct once.
2. Regeneration attempts are traceable in debug data.
3. The system never loops indefinitely.

### Blocker 3: Make Widget Message Persistence Safer

Objective:

Prevent partial conversation persistence when runtime execution fails mid-request.

Suggested implementation:

1. Wrap the widget message creation + conversation updates in transaction-aware logic where appropriate.
2. If full transaction wrapping is not practical because of external HTTP calls, add explicit failure markers or error-state messages.
3. Ensure the admin panel can distinguish incomplete runtime attempts from complete ones.

Likely files affected:

1. `app/Http/Controllers/ChatController.php`
2. `app/Models/Message.php`
3. `app/Models/Conversation.php`

Acceptance criteria:

1. No silent half-finished chat state remains after failure.
2. Runtime failures are visible to operators.
3. Conversation history remains internally coherent.

### Blocker 4: Increase End-to-End Runtime Test Coverage

Objective:

Add high-confidence tests around the real runtime path, not only isolated helpers and mocked flows.

Suggested implementation:

1. Add integration tests for `ChatPipelineService` behavior.
2. Add degraded-path tests for provider failure, empty output, validator failure, and strict Georgian fallback.
3. Add widget persistence tests around successful and failed runtime flows.
4. Add tests for low-confidence or ambiguous intent behavior once clarification logic exists.

Likely files affected:

1. `tests/Feature/`
2. `tests/Unit/`
3. `app/Services/Chatbot/ChatPipelineService.php`

Acceptance criteria:

1. Core runtime flow has real integration coverage.
2. Main failure paths are tested.
3. Refactoring fallback logic becomes safer.

## Important Improvements

These should follow immediately after blockers.

### Important 1: Move Chatbot Lab Run Execution To Background Jobs

Objective:

Make evaluation runs more scalable and less fragile for admins.

Suggested implementation:

1. Queue run execution.
2. Persist status transitions: pending, running, completed, failed, cancelled.
3. Add lightweight progress UI.
4. Add cancel or stop option if feasible.

Likely files affected:

1. `app/Services/Chatbot/ChatbotLabRunService.php`
2. `app/Http/Controllers/Admin/ChatbotLabController.php`
3. `app/Jobs/`
4. `resources/views/admin/chatbot-lab/runs/`

Acceptance criteria:

1. Large case runs do not depend on a single long HTTP request.
2. Admin can see run status clearly.
3. UI remains responsive during long runs.

#### Phase 4 Execution Blueprint

Step 1: Queue readiness and operator messaging

1. Detect whether the active queue driver is truly background-capable.
2. Distinguish between three states in the admin UI:
	- `sync` driver: runs work, but execute inline.
	- background-capable driver ready: runs continue outside the request.
	- misconfigured background driver: queued execution is selected, but required infrastructure is missing.
3. Add the missing `jobs` table migration so the `database` queue driver can be enabled without extra manual schema work.

Step 2: Run lifecycle tightening

1. Keep `pending -> running -> completed|failed` transitions explicit and centralized.
2. Prevent misleading queued status messages when the environment is still on `sync`.
3. Prepare service-level hooks for a later `cancelled` state.

Step 3: Lightweight progress visibility

1. Expose a small status payload for the run detail page.
2. Add refresh guidance or polling hooks without forcing a heavier frontend rewrite.
3. Keep pending/running/failed states readable from the list and detail views.

Step 4: Cancellation and follow-up UX

1. Add a safe cancel action only after lifecycle state handling is stable.
2. Prevent cancelling completed or failed runs.
3. Reflect cancellation clearly in run history and score cards.

Current implementation status:

1. Step 1 is completed.
2. Step 2 is completed.
3. Step 3 is completed.
4. Step 4 is completed.

### Important 2: Add Persistent Lab Session Mode

Objective:

Allow operators to reproduce real multi-turn drift inside Chatbot Lab.

Suggested implementation:

1. Support reusable lab conversations in addition to disposable runs.
2. Let admins continue an existing lab session.
3. Expose session reset explicitly.

Likely files affected:

1. `app/Services/Chatbot/ChatbotLabService.php`
2. `app/Http/Controllers/Admin/ChatbotLabController.php`
3. `resources/views/admin/chatbot-lab/index.blade.php`

Acceptance criteria:

1. Operator can test multi-turn conversation continuity.
2. Operator can intentionally reset context.
3. Debug output reflects session continuity.

Current implementation status:

1. Reusable manual lab sessions are implemented.
2. Operators can continue an active session or run disposable one-off prompts.
3. Session reset is exposed explicitly in the UI.

### Important 3: Make Debug Signals More Actionable

Objective:

Improve the operator's ability to understand what specifically failed and what action to take next.

Suggested implementation:

1. Add canonical failure taxonomy.
2. Separate search miss, validator block, provider issue, intent uncertainty, and policy fallback in the UI.
3. Add a recommended next action label where possible.

Likely files affected:

1. `app/Services/Chatbot/ChatbotLabService.php`
2. `resources/views/admin/chatbot-lab/index.blade.php`
3. `resources/views/admin/chatbot-lab/runs/show.blade.php`

Acceptance criteria:

1. Operator can tell whether the problem came from intent, search, generation, validation, or policy.
2. Failures are grouped consistently.
3. Debug output is easier to act on.

Current implementation status:

1. Manual Chatbot Lab now emits canonical actionable signals with issue source, severity, and recommended next action.
2. Evaluation run detail now surfaces heuristic operator signals per result row.
3. Operator-facing debug output is grouped more consistently across manual and batch QA flows.

### Important 4: Add Better Pre-Run Validation For Training Cases

Objective:

Improve case quality before execution.

Suggested implementation:

1. Add completeness checks for training cases.
2. Detect obvious duplicates.
3. Flag weak or missing expectations.

Likely files affected:

1. `app/Services/Chatbot/ChatbotTrainingCaseService.php`
2. `app/Http/Controllers/Admin/ChatbotLabController.php`
3. `resources/views/admin/chatbot-lab/cases/index.blade.php`

Acceptance criteria:

1. Weak test cases are identified before execution.
2. Admins get actionable validation messages.

Current implementation status:

1. Training cases now expose blocking vs warning diagnostics in the cases UI.
2. Duplicate prompt detection now includes normalized near-duplicate checks, not only exact matches.
3. Evaluation run start now performs preflight validation and blocks selected cases with missing expectations.
4. Runs UI now shows a structured preflight breakdown for blocking and warning cases.
5. Create and edit forms now show live preview diagnostics before save, including duplicate and expectation warnings.
6. Dataset quality improves over time.

### Important 5: Add Permission And Export Coverage

Objective:

Complete the basic hardening of the admin panel.

Suggested implementation:

1. Add non-admin authorization tests for all Chatbot Lab routes.
2. Add run detail rendering tests.
3. Add CSV export tests.

Likely files affected:

1. `tests/Feature/ChatbotLabWorkflowTest.php`
2. `tests/Feature/`

Acceptance criteria:

1. Admin-only protection is covered.
2. Export behavior is verified.
3. Run detail flow is safer to refactor.

Current implementation status:

1. Chatbot Lab feature coverage now exercises non-admin access across the major route surface, including case management, manual lab actions, run lifecycle actions, and reviewer actions.
2. Run detail rendering is covered with assertions for progress, reviewer workflow, export affordance, and actionable signal output.
3. CSV export is covered end-to-end, including filename and streamed content assertions.

## Nice-To-Have Improvements

These should be done after blockers and important items.

### Nice 1: Improve Product Selection Precision For Widget Cards

Objective:

Reduce silent wrong-card attachment when the response and product set are only loosely aligned.

Suggested implementation:

1. Improve product matching ranking.
2. Stop relying on weak first-match behavior.
3. Use response-intent and extracted entities more explicitly in product selection.

Likely files affected:

1. `app/Http/Controllers/ChatController.php`
2. `app/Services/Chatbot/SmartSearchOrchestrator.php`

Acceptance criteria:

1. Widget cards align more reliably to the final answer.
2. Wrong-card attachment becomes rarer.

Current implementation status:

1. Widget card selection no longer falls back to the first in-stock product for price, stock, or feature intents when the bot answer does not explicitly mention a product.
2. Response-aware ranking is now combined with intent entities so exact product matches can still attach a single card when the response is short but clearly about one model.
3. Ambiguous intent matches are now suppressed instead of attaching a misleading card, with focused widget feature coverage for both suppression and exact-match attachment.
4. SmartSearchOrchestrator now ranks fuzzy slug, brand/model, and keyword matches before truncation so the requested product and top candidates are no longer dependent on database return order.
5. Focused unit coverage now verifies exact brand/model ordering and closest fuzzy-slug ordering inside the orchestrator.

### Nice 2: Add Operator-Friendly Retry Actions In The Lab

Objective:

Reduce friction when reviewing failed answers.

Suggested implementation:

1. Add “rerun with same context”.
2. Add “rerun with validation constraints”.
3. Add “promote and rerun” workflow if helpful.

Acceptance criteria:

1. Operators can iterate faster from a failed case.
2. Fewer manual copy-paste loops are needed.

Current implementation status:

1. Manual Chatbot Lab results now expose quick retry actions for same-prompt and constrained reruns.
2. Evaluation run detail rows now support rerunning a result directly into Manual Lab without launching a new batch run.
3. Constrained reruns derive operator guidance from expectations, failed checks, fallback context, and judge notes.
4. Evaluation results can now be promoted into training cases and rerun immediately in one step.

### Nice 3: Add Performance And Degraded-Service Monitoring

Objective:

Make it easier to see when the runtime is healthy but slow, or safe but overly fallback-heavy.

Suggested implementation:

1. Track regeneration rate.
2. Track fallback categories over time.
3. Track provider failure rate separately from validator failure rate.
4. Track lab run duration by case count.

Acceptance criteria:

1. Performance issues become visible sooner.
2. Quality regressions are easier to detect.

Current implementation status:

1. Chatbot Lab evaluation results now persist fallback reason and regeneration metadata for later analysis.
2. Evaluation Runs index now shows an operational snapshot with response-time, fallback, regeneration, provider-issue, and duration-per-case summaries.
3. Run detail now shows a per-run health snapshot so degraded patterns are visible without exporting results.
4. Global chatbot quality counters now track slow responses, regeneration usage, and provider vs validator vs policy fallback categories.
5. Evaluation Runs index now raises monitoring alerts when fallback, provider, validator, or latency rates exceed basic thresholds.
6. Monitoring thresholds are now configurable instead of hardcoded.
7. Evaluation Runs index now shows a daily quality trend for response volume, fallback rate, and slow-response rate.
8. Omnichannel provider incidents where no response is generated now count separately from fallback-based provider issues.
9. Evaluation Runs monitoring now surfaces provider incident rates and alerts alongside fallback-based degradation.

## What To Remove Or Simplify

### Remove 1: Overuse Of Generic Fallback Text

Reduce the number of places that immediately return broad generic apologies when the system could clarify or regenerate.

### Remove 2: Synchronous Run Execution As The Default Admin Path

Keep it only if explicitly needed for tiny runs or local development.

### Remove 3: Weak First-Match Product Selection Heuristics

If product ambiguity remains high, suppress cards rather than attach misleading ones.

### Remove 4: Debug Noise That Does Not Lead To Action

Prefer compact, actionable labels over raw data overload.

## Recommended Implementation Order

### Phase 1: Runtime Reliability Foundation

Status: completed

Implementation note: fallback outcome metadata was centralized in the runtime result contract, validator failure now triggers one controlled regeneration attempt before the integrity fallback, widget debug output exposes fallback/regeneration state, targeted regression tests were added for validator-driven regeneration, and ChatPipelineService now routes guard, greeting, provider, strict-Georgian, and validator outcomes through a dedicated fallback strategy plus one shared finalization path for result metadata and metrics.

1. Centralize fallback logic.
2. Introduce canonical failure taxonomy.
3. Add one regeneration pass for validator failures.

### Phase 2: Safer Widget Runtime

Status: completed

Implementation note: widget message writes now use transaction-aware persistence blocks, pipeline exceptions no longer leave a silent half-finished chat state, explicit bot failure messages are persisted with failure metadata, a focused feature test covers the failure-persistence path, widget card attachment now prefers explicit response mentions or a strong unique intent match instead of weak first-result fallback, and SmartSearchOrchestrator now ranks product candidates before truncation so upstream search order is more deterministic.

1. Improve transaction and failure-state handling in widget message persistence.
2. Make incomplete runtime attempts observable.
3. Tighten widget card attachment rules where needed.

### Phase 3: Test Confidence Upgrade

Status: completed

Implementation note: real widget-flow feature coverage now includes validator-driven regeneration, provider-unavailable fallback, empty-model-output fallback, strict-Georgian fallback, explicit persistence of bot-side failure state when the runtime throws, and widget product-suppression heuristics. Admin-side coverage now also includes non-admin route protection, run-detail rendering, and CSV export. During this work, streamed-response compatibility in the security-header middleware was fixed so export routes no longer break under middleware.

1. Add end-to-end runtime integration tests.
2. Add failure-mode tests.
3. Add admin authorization, run detail, and export tests.

### Phase 4: Admin Lab Operational Hardening

Status: completed

Implementation note: Chatbot Lab run creation now supports queued execution through a dedicated background job, the controller/UI flow has been switched from immediate-complete semantics to queued semantics, run detail pages now surface pending/running/failed state more clearly, and feature coverage now verifies queued run creation and job dispatch.

1. Move runs to background execution.
2. Add status/progress handling.
3. Improve training case validation.

### Phase 5: Operator Workflow Improvements

Status: completed

Implementation note: persistent manual lab sessions, same-prompt/constrained reruns, promote-and-rerun, and actionable debug signals are now implemented across Manual Lab and evaluation result review flows.

1. Add persistent lab sessions.
2. Add rerun actions.
3. Improve debug usability.

### Phase 6: Observability And Cleanup

Status: completed

Implementation note: fallback/regeneration/provider monitoring is already surfaced in Chatbot Lab, the operator-facing manual/run detail UI now prefers human-readable fallback labels plus compact validation summaries over raw JSON noise while preserving advanced raw payload inspection on demand, and monitoring summaries now expose human-readable fallback labels instead of raw codes alone.

1. Add better metrics for fallback and regeneration behavior.
2. Remove leftover debug noise and weak heuristics.
3. Reassess system score after implementation.

## Definition Of Done For This Hardening Plan

This plan should be considered complete only when all of the following are true:

1. Runtime fallback behavior is centralized and explainable.
2. Validator failures can self-correct once before generic fallback.
3. Widget runtime no longer leaves silent partial state on failure.
4. Chatbot Lab runs do not rely on long synchronous HTTP execution.
5. Admin debug output clearly explains why an answer failed.
6. End-to-end runtime coverage is materially stronger than today.
7. The system can be re-scored at **8.5/10 or better** with confidence.

## Reassessment

Current overall score: **8.7/10**

Updated breakdown:

1. Runtime chatbot core: **8.6/10**
2. Admin testing panel / Chatbot Lab: **9.0/10**
3. Automated testing confidence: **8.4/10**

Reasoning:

1. Runtime fallback behavior is centralized and explainable, validator failures get one controlled regeneration pass, and widget runtime no longer leaves silent partial failure state.
2. Chatbot Lab now supports persistent sessions, queued run execution, cancellation, retry workflows, actionable debug signals, preflight validation, observability snapshots, and lower-noise operator diagnostics.
3. Runtime and admin coverage is materially stronger across fallback flow, strict Georgian enforcement, regeneration, widget persistence, run lifecycle, authorization, export, and product-selection heuristics.
4. Remaining risk is incremental tuning and ongoing regression monitoring, not missing core reliability or operator workflow foundations.

## First Implementation Slice

Recommended first slice for immediate execution:

1. Centralize fallback logic in the runtime pipeline.
2. Add validator-driven single regeneration pass.
3. Add integration tests for those two behaviors.

This is the highest-leverage first step because it improves user-visible answer quality, reduces avoidable fallbacks, and makes the rest of the system easier to reason about.

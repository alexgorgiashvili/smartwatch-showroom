# Chatbot Lab Replacement Plan

## Goal

Replace the current `admin/chatbot-tests` subsystem with a simpler, operator-friendly chatbot lab that cleanly separates:

1. Manual testing
2. Training case management
3. Evaluation run history

The new system should help tune and train the chatbot faster, with less UI noise, less hidden logic, and less coupling to disk-based JSON mutation.

## Why Replace The Current System

The existing `admin/chatbot-tests` flow is overloaded. It currently mixes:

1. Manual QA
2. Dataset mutation
3. Run orchestration
4. Feedback branching
5. Reindex actions
6. Charts/polling/status tracking
7. LLM judging

That makes day-to-day chatbot improvement slower than it should be.

## Product Direction

The replacement should be built as `Chatbot Lab` or `Chatbot Trainer`, not as another heavy QA dashboard.

Core principle:

`reproduce bad answer -> inspect debug -> update rule/prompt/data -> rerun -> save as training case`

## New Information Architecture

Build the new admin experience around 3 simple areas:

### 1. Manual Test

Purpose: fast prompt testing and debugging.

The operator should be able to:

1. Enter a prompt
2. Optionally add short multi-turn context
3. Choose channel/context mode if needed
4. Run the chatbot
5. See structured debug data
6. Save the result as a training case

The page should show:

1. Raw input
2. Normalized input
3. Intent
4. Intent confidence
5. Intent fallback flag
6. Validation passed/failed
7. Validation violations
8. Final reply
9. Selected products
10. Carousel attached/suppressed
11. Response time
12. Fallback reason

### 2. Training Cases

Purpose: maintain the chatbot's curated learning set.

Replace the JSON dataset with a database-backed table.

Each case should support:

1. Prompt
2. Optional conversation context
3. Expected intent
4. Expected keywords
5. Expected product slug(s)
6. Expected price/stock expectations
7. Reviewer notes
8. Tags
9. Active/inactive flag

The operator should be able to:

1. Create case manually
2. Create case from manual test result
3. Edit case
4. Search/filter by tag/status
5. Enable/disable case

### 3. Evaluation Runs

Purpose: run selected cases in batches and track chatbot quality over time.

This should be lighter than the current system.

The page should support:

1. Run selected cases
2. Run by tag/category
3. View compact run history
4. Open run details
5. Review pass/fail reasons

Default mode should use deterministic checks first.
LLM judge should be optional, not the default path.

## Recommended Data Model

### Keep Or Reuse

1. `ChatbotTestRun`
2. `ChatbotTestResult`
3. `TestRunnerService`
4. `LlmJudgeService` as optional

### Add

Create a new database-backed training cases table, for example:

`chatbot_training_cases`

Suggested fields:

1. `id`
2. `title`
3. `prompt`
4. `conversation_context_json`
5. `expected_intent`
6. `expected_keywords_json`
7. `expected_product_slugs_json`
8. `expected_price_behavior`
9. `expected_stock_behavior`
10. `reviewer_notes`
11. `tags_json`
12. `is_active`
13. `source`
14. `created_by`
15. `timestamps`

## Legacy System Decomposition

### Remove From The Old Flow

The new system should remove these patterns from day-to-day operator workflow:

1. Disk mutation of `database/data/chatbot_golden_dataset.json`
2. 5-branch feedback form logic
3. Tight coupling between result review and reindex actions
4. Polling-heavy dashboard-first UX
5. Hidden run/debug reasons

### Keep Only If Still Useful

1. Historical run/result records
2. Batch execution engine
3. Optional LLM scoring
4. Optional export

## Controller/Service Architecture

### Recommended New Structure

1. `Admin/ChatbotLabController`
   Purpose: pages, submissions, manual runs, simple orchestration

2. `ChatbotLabService`
   Purpose: execute prompt, gather debug data, prepare result payloads

3. `ChatbotTrainingCaseService`
   Purpose: CRUD + conversion from result to training case

4. `ChatbotEvaluationService`
   Purpose: run selected training cases and store run/results

5. `ChatbotDebugPresenter` or equivalent formatter
   Purpose: present intent, validator, product, carousel, fallback facts clearly

## UI Plan

### Page 1: Chatbot Lab Home

Simple landing page with 3 entry points:

1. Manual Test
2. Training Cases
3. Evaluation Runs

### Page 2: Manual Test

Main daily-use page.

Should include:

1. Prompt textarea
2. Optional previous-messages input block
3. Run button
4. Final reply panel
5. Structured debug panel
6. Product/card preview panel
7. Save as training case button

### Page 3: Training Cases

Should include:

1. Table/list of cases
2. Filters by active/tag/source
3. Edit form
4. Quick duplicate/delete actions
5. Run selected action

### Page 4: Evaluation Runs

Should include:

1. Run history
2. Summary metrics
3. Run details
4. Failure reasons
5. Reviewer notes

Do not rebuild the current chart-heavy UI unless a real need appears later.

## Suggested Workflow

### Daily Chatbot Improvement Loop

1. Reproduce a bad user question in Manual Test
2. Inspect debug data
3. Identify whether failure came from:
   1. intent detection
   2. retrieval
   3. model output
   4. validator
   5. UI product attachment
4. Adjust rule/prompt/data/code
5. Rerun immediately
6. If important, save as training case
7. Periodically run selected evaluation suite

## Migration Strategy

### Phase 1

Build the new `Chatbot Lab` pages and services in parallel with legacy `admin/chatbot-tests`.

### Phase 2

Import existing JSON dataset into the new DB-backed training cases table.

### Phase 3

Validate parity on a subset of important cases.

### Phase 4

Switch admin navigation from `Chatbot Tests` to `Chatbot Lab`.

### Phase 5

Delete or archive the old `admin/chatbot-tests` UI, controller branches, polling endpoints, and JSON mutation helpers.

## What Must Still Be Visible In The New System

Every meaningful run should expose:

1. Final response
2. Intent type
3. Intent fallback
4. Validation passed/failed
5. Validation violations
6. Fallback reason
7. Products found
8. Products attached
9. Carousel suppressed or not
10. Response time

Without this, chatbot tuning becomes guesswork.

## Practical Recommendation

Build implementation in this order:

1. Manual Test page
2. Training cases table/model/editor
3. Save-to-case flow from manual tests
4. Evaluation runs page
5. Legacy migration/import
6. Legacy deletion

This gives immediate value early, instead of waiting for a full replacement before the system becomes useful.

## Success Criteria

The replacement is successful when:

1. You can test a bad prompt in under 30 seconds
2. You can see exactly why the chatbot failed
3. You can save that case for future regression prevention
4. You can rerun a focused subset quickly
5. You no longer need to edit JSON files or navigate a complex admin QA dashboard

## Implementation Breakdown

This section converts the product/architecture plan into an execution plan.

Each phase should be treated as independently reviewable and testable.

### Phase 0: Scope Freeze And Inventory

Goal: prepare replacement work without breaking existing admin tooling.

Tasks:

1. Confirm final naming: `Chatbot Lab` or `Chatbot Trainer`
2. Mark legacy `admin/chatbot-tests` as deprecated in internal notes
3. Inventory reusable pieces from the old system
4. Decide which legacy data must remain visible after migration
5. Define MVP scope for the first usable version

Likely files involved:

1. `routes/web.php`
2. `app/Http/Controllers/Admin/ChatbotTestController.php`
3. `app/Models/ChatbotTestRun.php`
4. `app/Models/ChatbotTestResult.php`
5. `database/data/chatbot_golden_dataset.json`

Acceptance criteria:

1. MVP scope is explicitly chosen
2. Team agrees what is reused vs replaced
3. No production behavior is changed yet

### Phase 1: Skeleton And Navigation

Status: completed

Goal: create the new admin area without deleting the old one.

Tasks:

1. Add new admin routes for the lab home and subpages
2. Create `ChatbotLabController` skeleton
3. Add admin sidebar/navigation link
4. Create empty Blade views for:
   1. Lab home
   2. Manual test
   3. Training cases
   4. Evaluation runs
5. Keep legacy `chatbot-tests` routes active during this phase

Suggested new files:

1. `app/Http/Controllers/Admin/ChatbotLabController.php`
2. `resources/views/admin/chatbot-lab/index.blade.php`
3. `resources/views/admin/chatbot-lab/manual.blade.php`
4. `resources/views/admin/chatbot-lab/cases/index.blade.php`
5. `resources/views/admin/chatbot-lab/runs/index.blade.php`

Likely edits:

1. `routes/web.php`
2. `resources/views/admin/partials/sidebar.blade.php`

Acceptance criteria:

1. New admin pages open successfully
2. Navigation shows the new lab link
3. Legacy pages still work unchanged

### Phase 2: Manual Test Backend

Status: completed

Goal: make the new lab useful immediately with single-prompt execution.

Tasks:

1. Create `ChatbotLabService`
2. Add method to execute a manual prompt through `ChatPipelineService`
3. Capture debug metadata from the pipeline
4. Return structured result DTO/array to the controller
5. Persist optional manual test history if desired

Suggested new files:

1. `app/Services/Chatbot/ChatbotLabService.php`
2. `app/Services/Chatbot/ChatbotDebugPresenter.php`

Likely reused services:

1. `app/Services/Chatbot/ChatPipelineService.php`
2. `app/Services/Chatbot/ResponseValidatorService.php`
3. `app/Services/Chatbot/IntentAnalyzerService.php`

Acceptance criteria:

1. A prompt can be submitted from the new page
2. Final response is shown
3. Intent/debug data is visible
4. Validation violations are visible
5. Attached/suppressed products are visible

### Phase 3: Manual Test Frontend

Status: completed

Goal: make the manual page comfortable for daily chatbot debugging.

Tasks:

1. Add prompt textarea and submit action
2. Add optional multi-turn context input
3. Add result panel for final reply
4. Add structured debug sections
5. Add product preview/cards preview block
6. Add action button: `Save as training case`

Likely files:

1. `resources/views/admin/chatbot-lab/manual.blade.php`
2. Optional JS partial or inline script for manual test page behavior

Acceptance criteria:

1. Operator can test a bad prompt in one screen
2. Result page does not require opening logs
3. Debug information is readable enough for real tuning work

### Phase 4: Training Cases Table And CRUD

Status: completed

Goal: replace JSON-based case management with DB-backed editable cases.

Tasks:

1. Create migration for `chatbot_training_cases`
2. Create model for training cases
3. Add create/edit/delete/list routes and controller methods
4. Add form validation
5. Add search/filter/tag/active-state support
6. Add create-from-manual-result flow

Suggested new files:

1. `database/migrations/*_create_chatbot_training_cases_table.php`
2. `app/Models/ChatbotTrainingCase.php`
3. `app/Services/Chatbot/ChatbotTrainingCaseService.php`
4. `resources/views/admin/chatbot-lab/cases/form.blade.php`
5. `resources/views/admin/chatbot-lab/cases/show.blade.php`

Acceptance criteria:

1. Cases can be created without touching JSON files
2. Cases can be edited and tagged
3. Cases can be activated/deactivated
4. Manual test results can be promoted into cases

### Phase 5: Evaluation Run Simplification

Status: completed

Goal: rebuild run execution around the new training cases model with less complexity.

Tasks:

1. Create a simple run entry point from selected cases
2. Reuse `TestRunnerService` where possible
3. Support deterministic grading by default
4. Keep LLM judge as optional toggle
5. Store run summaries and detailed results
6. Build simplified run detail page

Likely reused pieces:

1. `app/Models/ChatbotTestRun.php`
2. `app/Models/ChatbotTestResult.php`
3. `app/Services/Chatbot/TestRunnerService.php`
4. `app/Services/Chatbot/LlmJudgeService.php`

Potential edits:

1. `app/Jobs/RunTestSuiteJob.php`
2. New run-oriented methods in `ChatbotLabController`
3. New run views under `resources/views/admin/chatbot-lab/runs/`

Acceptance criteria:

1. Operator can run selected cases from the new UI
2. Run results are readable without the old dashboard
3. LLM judge is optional and off by default

### Phase 6: Legacy Dataset Import

Status: completed

Implementation note: legacy JSON import command added and executed successfully. 84 legacy cases were imported into the DB-backed `chatbot_training_cases` table.

Goal: migrate old test cases into the new cases table.

Tasks:

1. Build importer from `database/data/chatbot_golden_dataset.json`
2. Map legacy fields into the new schema
3. Mark imported source as `legacy_json`
4. Verify imported sample manually
5. Freeze disk-based mutations after import is trusted

Suggested new files:

1. `app/Console/Commands/ImportChatbotTrainingCases.php`

Acceptance criteria:

1. Legacy cases exist in DB-backed form
2. New UI can browse imported cases
3. JSON file is no longer part of the daily edit loop

### Phase 7: Debug-First Review Workflow

Status: completed

Implementation note: Chatbot Lab run results now support `Save observation`, `Save and mark resolved`, and `Promote to training case` actions without triggering legacy JSON mutation or reindex side effects.

Goal: replace the current 5-action feedback form with a simpler reviewer loop.

Tasks:

1. Add `Save observation` action on results
2. Add `Promote to training case` action
3. Optionally add `Mark as resolved` state
4. Keep reviewer notes visible on result pages
5. Remove hidden side effects like automatic dataset mutation on feedback

Acceptance criteria:

1. Reviewing a bad answer takes fewer steps
2. No direct JSON mutation happens from UI feedback
3. Reviewer notes remain attached to runs/results

### Phase 8: Legacy Feature Decommissioning

Status: completed

Implementation note: legacy `admin/chatbot-tests` routes, sidebar entry, controller, scheduler flow, and views have been removed. Export and score-card display were moved into Chatbot Lab so the old UI is no longer required.

Goal: remove the old `admin/chatbot-tests` system after parity is reached.

Tasks:

1. Remove legacy routes
2. Remove or archive legacy views
3. Remove dataset mutation helpers from the old controller
4. Remove polling-only UI code if no longer needed
5. Decide whether old controller/service code is deleted or archived

Likely files affected:

1. `routes/web.php`
2. `app/Http/Controllers/Admin/ChatbotTestController.php`
3. `resources/views/admin/chatbot-tests/*`

Acceptance criteria:

1. Admin users no longer depend on `admin/chatbot-tests`
2. All essential workflows exist in the new lab
3. Old complexity is actually removed, not duplicated

### Phase 9: Post-Cutover Hardening

Status: completed

Implementation note: added focused Chatbot Lab automated coverage for admin page access, manual test rendering, training case CRUD, deterministic run execution, and review-to-promotion workflow in `tests/Feature/ChatbotLabWorkflowTest.php`.

Goal: make the new system stable enough for regular chatbot tuning.

Tasks:

1. Add feature tests for the new manual test flow
2. Add CRUD tests for training cases
3. Add run execution tests against selected cases
4. Review permissions/auth on new admin pages
5. Review performance if LLM judge or queueing remains enabled

Acceptance criteria:

1. New lab has core automated coverage
2. New lab is safe for daily admin use
3. Debug data remains stable across iterations

## Recommended MVP Cut

If implementation needs to stay tight, the MVP should include only:

1. New admin navigation
2. Manual Test page
3. DB-backed Training Cases CRUD
4. Save manual result as training case
5. Minimal Evaluation Runs page using existing run/result models

Everything else can follow later.

## Phase Dependencies

Recommended order:

1. Phase 1 before everything
2. Phase 2 before Phase 3
3. Phase 4 before Phase 5
4. Phase 6 before Phase 8
5. Phase 9 after cutover begins

Safe parallel work:

1. Manual Test UI and Training Case schema can overlap after skeleton exists
2. Legacy import command can be prepared while the new pages are being built

## Per-Phase Definition Of Done

Each phase should only be considered done when all of these are true:

1. Code paths are reachable from admin UI
2. Relevant tests pass
3. No hidden dependency on legacy JSON editing remains for that phase
4. Operator workflow is simpler than the equivalent legacy flow

## Open Product Decisions Before Implementation Starts

These should be answered before or during Phase 1:

1. Final product name: `Chatbot Lab` or `Chatbot Trainer`
2. Whether `ChatbotTestRun` and `ChatbotTestResult` remain as-is or are renamed later
3. Whether LLM judge is available in MVP or postponed
4. Whether run execution remains queued or becomes sync for selected-case runs
5. Whether historical legacy runs need a dedicated archive page

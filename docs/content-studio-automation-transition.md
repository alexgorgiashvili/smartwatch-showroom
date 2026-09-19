# Content Studio automation transition (manual, paused)

The prior paused automation definitions were inspected before transition. Their non-secret schedule/name metadata was preserved in the task audit, then the two exact duplicates (`mytechnic-browser-gpt-dispatcher-2` and `mytechnic-growth-scout-planner-2`) were safely retired. The remaining three roles were updated in-place and are all **PAUSED**; this repository does not activate them.

Create exactly these three **PAUSED** roles, all using `Asia/Tbilisi`, and keep them paused until an owner completes an end-to-end staging verification:

| Role | Local schedule | Safe run contract |
| --- | --- | --- |
| Scout | every 3 hours at `:05` | Produce one evidence-backed candidate only; submit no more than one intake item. |
| Generator / Dispatcher | every 2 hours at `:20` | Turn one approved candidate into one draft/revision only; never approve, schedule, publish, or write live records. |
| Reviewer | hourly at `:40` | Check one submitted item and leave evidence/checklist notes only; owner approval remains in the admin UI. |

The dispatcher must enforce the caps using the intake API response: one bilingual article and no more than three social packages per ISO week. Tokens need only `content-studio:submit`; issue them through a one-time owner-controlled procedure, store them outside this repository, and never include them in prompts or logs.

Manual verification checklist: create a sandbox campaign; submit an article plus independent Facebook/Instagram drafts; confirm a new revision clears approval; approve each channel separately; test an immediate and a scheduled release in `Asia/Tbilisi`; force one Meta failure and confirm retry does not create a second `facebook_posts` record; confirm all roles remain PAUSED afterwards.

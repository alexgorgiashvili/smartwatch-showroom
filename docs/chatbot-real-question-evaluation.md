# Website chatbot: customer-question evaluation (2026-09-26)

## Scope and privacy

- Read-only local conversation inventory: 122 customer text messages from the website widget, 20 Facebook, 15 Instagram, and 2 WhatsApp. The website messages include 22 question-like price messages, 8 SIM/network, 7 delivery, and smaller stock, payment, warranty, calling, GPS, and water-resistance groups. These counts overlap when one message has multiple topics.
- Evaluated 17 short, manually reviewed **paraphrases of observed customer question types**, never raw transcripts or personal details. These are representative questions, not verbatim customer quotations or an independent random sample. The previous 120 frozen scenarios are synthetic and were not used as evidence of live correctness here.
- Requests went through the local Laravel /chatbot route and the real gpt-6-luna API. All writes were to an isolated in-memory SQLite database. No production chatbot request, database write, migration, or deploy was performed.
- The 17 questions covered delivery fee/time, cash payment, warranty, model exchange, SIM, video calling, price, stock, installments, refund, waterproofing, battery, GPS, network compatibility, same-day regional delivery, and setup app. The catalog was intentionally empty for these cases, so hardware claims required a clarification.
- Four further route-level questions used two **fictional SQLite products** with different prices and stock states. They test product-specific evidence handling; they do not verify current production inventory.

## Findings and changes

| Finding | Before | Change | Recheck |
| --- | --- | --- | --- |
| Money-back wording missed policy lookup | A refund question received only a support referral, omitting the verified conditional model-exchange policy. | Added refund wording triggers and clarified the distinction in widget knowledge. | Luna stated that a refund cannot be promised and described the 14-day conditional model exchange. |
| Unverified device details | Generic SIM and setup answers included speculative examples or implied that a model name alone guarantees an exact answer. The setup reply requested a photo that the text-only widget cannot accept. | Added widget-only evidence and text-input instructions. | SIM asks for a model; setup asks for a model name/link and says the app must be checked. |
| Cross-product stock/price validation | A price or stock value present for any catalog product could validate a claim about another model. A requested out-of-stock model could be omitted from validation context after recommendation filtering. | Kept the named requested product in widget validation evidence and bound stock/price claims to the named or requested product. | Wrong cross-product claims are rejected; a correct out-of-stock claim and correct per-product prices pass. |

The first 17 route responses included three actionable quality issues above. Policy answers for delivery, cash payment, 4G warranty and conditional exchange matched the verified site terms. No unsupported numeric price or stock promise was observed with an empty catalog. Focused Luna rechecks passed after fixes. This is a small targeted review, not a measured overall accuracy rate.

## Checks

- Targeted SQLite automated suite: 31 tests, 93 assertions passed, including route-level wrong-price/stock rejection and valid out-of-stock response.
- Real Luna rechecks used the same local /chatbot route. The final setup answer requested a model name or product link and did not promise an unverified app name.
- Paid requests stayed well below the previously authorized $3 ceiling by limiting the number of cases and disabling the auxiliary intent model in the evaluation. Exact token-based aggregate spend for this manual run was not captured, so no cost metric is claimed.

## Remaining limits

- Current production catalog contents and physical stock were not copied into the test database. Product-specific production answers still need a read-only catalog review and a follow-up isolated test with those public fields.
- Historical Messenger data informed the earlier topic audit, but this run selected questions from local channel records only. Instagram history remains inaccessible through the configured API.
- The owner approved deployment after the isolated evaluation. Commit `14cd6b0` was fast-forwarded to production on 2026-09-26 without a migration, cache clear, or changes to existing unrelated server files. Production configuration still selects `gpt-6-luna` for 100% of widget conversations and `gpt-4.1-mini` for social channels.
- A single clearly marked test question sent through the public live `/chatbot` endpoint returned HTTP 200. The answer withheld a refund promise and explained the conditional 14-day model exchange. This smoke test created an ordinary production chat record and admin notification; it is not a full production quality sample.
- Public home and catalog pages returned HTTP 200. A read-only production runtime check loaded the refund policy, rejected a cross-product false price and stock claim, and accepted the correct out-of-stock claim.

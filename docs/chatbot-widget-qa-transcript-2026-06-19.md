# Chatbot Widget QA Transcript

Date: 2026-06-19

This transcript was captured from the local widget after the cache and cache-hit validation fixes.

## 1. 2G models

**User:** `რომელი 2G მოდელები გაქვთ?`

**Bot:** `აქ არის 2G საბავშვო სმარტ საათები, რომლებიც მარაგშია:`

- Q12
- Q19
- Q15
- Q21

**Debug:** `intent=recommendation`, `validation_passed=true`, `products_found=4`, `products_attached=3`

## 2. Q19 price

**User:** `Q19-ის ფასი?`

**Bot:** `Q19 — 2G საბავშვო სმარტ საათი კამერითა და SOS-ღილაკით, ფასდაკლებით: 79.00 ₾ → 59.00 ₾, მარაგშია.`

**Debug:** `intent=price_query`, `validation_passed=true`, `products_found=1`, `products_attached=1`

## 3. 4G models

**User:** `და 4G მოდელები?`

**Bot:** `აქ არის 4G საბავშვო სმარტ საათები, რომლებიც თქვენი ყურადღების ღირსია და მარაგშიც არის:`

- Wonlex CT23
- Wonlex CT27
- T46
- Wonlex CT24
- T53
- X01
- Wonlex KT34
- A49

**Debug:** `intent=recommendation`, `validation_passed=true`, `products_found=8`, `products_attached=3`

## 4. Discounted models

**User:** `ფასდაკლება კიდევ რომელ მოდელებზე გაქვთ?`

**Bot:** `ფასდაკლება გვაქვს შემდეგ მოდელებზე:`

- Q19
- Wonlex CT24

**Debug:** `intent=price_query`, `validation_passed=true`, `products_found=2`, `products_attached=2`

## Notes

- Cache key version was bumped to invalidate older degraded responses.
- Supervisor cache hits now return validated metadata so the pipeline does not convert cached replies into fallback text.
- Degraded replies are no longer written into exact-match cache.

# Georgian UTF-8 Safe Editing Rule

Use this workflow whenever a file contains Georgian text or may be edited in Georgian.

## Rules

- Keep all source files in `UTF-8` encoding.
- Prefer `apply_patch` for text edits.
- Avoid large shell-based search/replace rewrites on Blade/PHP/JS files.
- Do not use console output as proof that Georgian text is broken; verify in the browser or rendered HTML.
- If a file already looks corrupted, do one clean UTF-8 rewrite, then switch back to small patches.
- For any edit touching Georgian copy, re-check the rendered page or raw HTML response.
- If a tool produces escaped Unicode like `\u{...}`, convert it back to normal Georgian text before finishing.

## Recommended practice

1. Small text change: use `apply_patch`.
2. Large copy block: rewrite the block once in UTF-8, then stop.
3. Verification: confirm both source file and live render.
4. If the console output is weird but the browser render is correct, trust the browser render.

## Short version

`UTF-8 + small patches + browser verification`

## One-line rule

Always edit Georgian text in UTF-8, keep changes small, and verify the rendered page before finishing.

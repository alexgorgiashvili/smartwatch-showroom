# AGENTS.md instructions

## Browser automation preference
- When a task mentions `@chrome` or depends on browser state, prefer the user's existing Chrome profile/session first.
- Check open Chrome tabs before trying a fresh login or a new browser backend.
- If the Chrome bridge/runtime fails, use the safest read-only fallback available and report the fallback clearly instead of silently switching behavior.

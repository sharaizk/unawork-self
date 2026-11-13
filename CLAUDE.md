# CLAUDE.md

The full project guide lives in `AGENTS.md` and is shared with other AI tools. It is imported here, so it is always in context:

@AGENTS.md

---

## Claude Code–specific working rules

### Before writing code
- Read the files in AGENTS.md §10 that relate to the area you're changing, plus the nearest existing feature, and copy its conventions (layout, modal pattern, route group, Service/FormRequest style).
- Pick the frontend approach with the decision table in AGENTS.md §5.1. **The answer is Blade unless the user explicitly says otherwise.** If someone asks for Vue/Inertia/Alpine/Tailwind on a live screen, point out that the build pipeline isn't wired (AGENTS.md §6) and confirm before proceeding.
- For tasks touching more than ~3 files, outline the routes → controller/service → views → JS/CSS changes before implementing.

### While working
- Make surgical edits. Legacy Taskly files are huge (`ProjectController.php`, `setting.blade.php`), so edit the specific block instead of rewriting.
- Keep backend and frontend contracts explicit. When you add a view, list the variables the controller must pass. When you add an AJAX call, state the endpoint, its method, and the JSON shape on success and on 422.
- When you add an admin page, also check:
  - the sidebar entry
  - permission gating
  - the `client.*` route twin
  - `__()` strings in `resources/lang/en.json`

### Hard stops: ask the user first
- Pushing, merging, or committing to `dev` or `main` (both auto-deploy).
- Running migrations or the test suite against any database you haven't confirmed is disposable.
- Running `npm` builds, changing `webpack.mix.js`, `vite.config.js` or `package.json`, or deleting the dead scaffolding listed in AGENTS.md §3.
- Adding Composer or npm dependencies.

### Git
- Branch from `dev` using the ticket key (`UNW-123`). Commit messages follow the existing style: `UNW-123: short description`.
- Only commit or push when the user asks.

### Finishing
- Run `php -l` on the PHP files you touched. Say what you verified (route list, page loaded, console clean) and what you could not.
- If you learned something that contradicts or extends AGENTS.md, update AGENTS.md (not this file) in the same change.

# CLAUDE.md

## Working preferences

- Do not change existing functionality. If a change would alter current behavior, ask me first before making it.

## Frontend build (shared hosting has no Node)

- React dashboard source lives in `frontend/`. Build locally with `cd frontend && npm install && npm run build`; it writes to `public/template/assets/react/`. Commit that built output, since the server cannot build it.
- Admin theme ("Aurum", light by default with a dark toggle): `public/template/assets/css/aurum-theme.css` plus the dark-mode colour adapter `public/template/assets/js/aurum-theme.js`, both loaded by `app/Views/admin/layouts/main.php`.

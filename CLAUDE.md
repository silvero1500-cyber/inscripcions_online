# CLAUDE.md — Inscripcions Online (WeRun)

Guia del projecte per a l'assistent. Plataforma **SaaS d'inscripcions a curses populars**.
Producció: **https://werun.cat/inscripcions/**. Marca: **WeRun**. UI en **català** (amb traducció al castellà).

## Stack i arquitectura
- **PHP 8.3+**, mini-MVC propi (sense framework). Autoload PSR-4 `App\` → `app/`.
- Dependències (composer): PHPMailer (correu SMTP), PhpSpreadsheet (imports Excel/CSV).
- Front controller únic: `index.php` → `bootstrap/routes.php` (Router propi a `app/Core/Router.php`).
- BD **MySQL/InnoDB** via `App\Core\Database` (singleton PDO, prepares reals). Connexió en UTC; PHP en `Europe/Madrid`. Helper `format_datetime_local()` per mostrar hora local.
- Vistes PHP planes a `app/Views/` (render via `App\Core\View`). Layout admin: `app/Views/layouts/admin.php`.

### Carpetes
- `app/Controllers` · `app/Models` · `app/Core` (Auth, Router, Database, Lang, Csrf, Validator…) · `app/Services` (EmailService, Migrator, BackupService, ImageUploader, QrService…) · `app/Views`.
- `database/` — `schema.sql` (esquema complet) + `migration_NNN_*.sql` (incrementals).
- `lang/ca.php`, `lang/es.php` — traduccions. `bootstrap/`, `config/`, `public/assets/` (css/js/img).

## Rols d'usuari (`usuarios.rol`)
- `superadmin` — accés total.
- `organizador` — només els seus events (taula `organizador_evento`).
- `recollida` — NOMÉS `/admin/recollida` dels events assignats (tancat per `Auth::recollidaScope`).
- `export` — NOMÉS `/admin/export` (descàrrega CSV dels events assignats; `Auth::exportScope`).

## Convencions importants
- **Idioma UI**: textos fixos via `t('clau')` (fitxers lang). Textos de camps personalitzats (BD, en català) es tradueixen amb `tc('text')` → clau `custom.<text>` a `es.php`. Quan aparegui contingut nou d'admin, revisar la traducció castellana.
- **Missatges de commit en català.**
- **Syncs d'editor: MAI DELETE+INSERT** si altres taules referencien els IDs (FK CASCADE). Fer upsert per id; en eliminar, si té dades dependents, desactivar (`activo=0`) en lloc d'esborrar. (Un DELETE+INSERT va destruir ~324 respostes d'inscrits; corregit a `CampoPersonalizado::syncForEvento`.)
- **Dorsals i llista d'espera són MANUALS**: no construir assignació automàtica de dorsals ni correus automàtics de llista d'espera.
- Inscripcions infantils: no s'exigeix DNI ni xip groc del menor (es demanen les dades del tutor). Un pagament (`pagos`) no pot quedar orfe (FK NOT NULL + RESTRICT).

## Desplegament (hosting només-FTP, sense SSH)
- **Deploy = FTP sync** dels fitxers modificats amb `.deploy/ftp-sync.ps1` (PowerShell). Hi ha un hook que ho fa en acabar el torn.
- **Migracions de BD**: aplicar des de `/admin/migracions` (UI superadmin, taula de control `schema_migrations`). Evitar runners manuals: si s'apliquen per fora, la UI les marca "Pendent" encara que ja hi siguin. NO reaplicar una migració ja aplicada (pot petar per duplicat).
- **Secrets** (credencials FTP, tokens, Redsys, SMTP): a `DEPLOY_SECRETS.local.md` (NO al repo) i al `.env` del servidor. Mai posar-los en fitxers versionats.
- Assets amb cache-busting: `asset()` afegeix `?v=<mtime>` (evita servir JS/CSS antic al mòbil).
- Còpies de seguretat: `BackupService` genera CSV dels events actius cada 12h a `storage/backups/` (retenció 14 dies), accessible a `/admin/backups`.

## Funcionalitats clau
- Inscripció pública individual i de grup (`InscripcionController`), pagament Redsys (`PagoController`), QR + check-in (`CheckinController`), recollida de dorsals (`RecollidaController`).
- Admin: events (`EventoController`, amb KPIs), inscrits (`InscritosAdminController`: grid editable, export CSV, eliminar), descomptes, tenda, incidències (`IncidenciasController`: bústia del formulari públic).
- Consentiment: una sola casella obligatòria "accepto la política de privacitat i el reglament".

## Notes de treball
- Confirmar abans d'escriptures destructives a producció (dry-run → confirmació → real).
- No fer push al remot sense petició explícita; commits locals periòdics com a backup, sí.

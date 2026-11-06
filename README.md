# Strathyre Darts League

Table, fixtures, results and subs for a 20-player darts league. Single-leg 301 matches, one league night a week (Friday).

- **Public site** (`/`): league table, next week's fixtures, last week's results, catch-ups, player pages (with each player's highest checkout and subs), finance (balance, subs owed, income per week, expenses), past seasons.
- **Admin** (`/admin`, one login: username `admin`): players, fixtures, results, expenses, subs payments, settings, season reset. When logged in, the public pages also show Edit buttons on results.

Built the same way as the Kind booking site: Laravel, Blade, server-rendered forms, one shared login, public pages and admin reading the same database. No emails, queue or scheduler, and no Node build: the CSS and JS are plain files in `public/`.

## Rules

- A win scores the **win points**, a loss the **loss points** (Settings). Nothing is assumed: the public site shows "Coming soon" until both, the subs amount and the fixtures are set.
- A result is the winner and their **checkout**: the score they finished from, with three darts to go (2 to 170). It has no effect on the table. The best checkout is shown on the player's page and for the whole league on Home.
- Table order: points. Players level on points are split by head-to-head (who beat whom; with three or more, by wins against each other). If none has been played, or they all beat each other in a circle, they go alphabetically during the season. Once every match is played, anyone still level shares a position (a play-off settles it, off the site). The highest-placed woman and man (once they've played) get the leader badges.
- Every pair meets once a season. Pasting `Callum F beat Isla C 121` finds that pair's one fixture, in any week, and the result counts under that fixture's week.
- Submitting a week closes it: fixtures in it with no result become **catch-ups**. "No results this week" does the same for all of them.
- The Home page's "Week N" stays on the first week whose results haven't been submitted. It shows a **Results pending** tag after 10pm on league night (`config/league.php`).
- Subs are due from each league night's date for every player, played or not, including nights with no results. Weeks off have no league night and no subs. Payments are recorded as amounts (Admin › Subs), in any size, and cover a player's oldest unpaid weeks first, so £5 at £2 a night is two weeks and half of a third. What someone owes is what's due so far minus everything they've paid; paying ahead shows as being ahead.
- The three league rules (points for a win, points for a loss, subs per night) can each be cleared in Settings. The public site shows "Coming soon" until all three are set.
- Fixtures: a valid round robin, ordered so strong-v-strong matches (rank × rank, squared) come last. With an odd number of players someone has a bye each week. Set a maximum number of league nights below a full season and some players play twice on a night.
- Resetting a season keeps its final table and results (public, under Past seasons), carries the money balance forward, and starts fresh. A season can also be deleted permanently (Admin › Season) to clear out test data.

## Code map

| | |
|---|---|
| `app/Services/League.php` | Table (with head-to-head), leaders, checkouts, current week, catch-ups, all worked out from fixtures and results |
| `app/Services/Finance.php` | Subs due and owed from payments, balance, per-week and per-player figures |
| `app/Services/FixtureGenerator.php` | Round robin with big matches towards the end, byes, doubles |
| `app/Services/ResultSubmission.php` | Parses pasted results, previews, saves, "no results" |
| `app/Services/PlayerImport.php`, `FixtureImport.php` | CSV checks before anything is saved |
| `app/Services/SeasonManager.php` | Reset (archive + carry balance) and hard delete |
| `app/Http/Controllers/Admin/*` | Admin screens |
| `public/css/app.css` | Design: black public pages, off-white admin and finance (`body.light`) |

## Local development

Plain PHP 8.3+ and SQLite:

```
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed   # sets up the first season (starts Fri 13 Nov 2026); no players or results
php artisan darts:admin      # create the admin login
php artisan serve
```

Or DDEV (`.ddev/config.yaml`, MySQL like production): `ddev start && ddev composer install && ddev artisan migrate --seed`.

Tests: `php artisan test`.

## Deploying on Forge

1. **Site:** new site of type **Laravel**, PHP 8.3, web directory `/public`, connected to this repo. Create a MySQL database and user.
2. **Environment**, starting from `.env.example`:
   - `APP_KEY` (`php artisan key:generate --show`), `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`
   - `DB_CONNECTION=mysql` and the `DB_*` details
   - `SESSION_SECURE_COOKIE=true`
3. **Deploy script** (no Node build needed):
   ```
   $CREATE_RELEASE()
   cd $FORGE_RELEASE_DIRECTORY
   $FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
   $FORGE_PHP artisan migrate --force
   $FORGE_PHP artisan optimize
   $ACTIVATE_RELEASE()
   ```
   No queue worker or scheduler is needed.
4. **First run:** `php artisan db:seed --force`, then `php artisan darts:admin`. In the admin: Settings (points, subs), Players (upload CSV), Fixtures (generate or upload), then results each week.

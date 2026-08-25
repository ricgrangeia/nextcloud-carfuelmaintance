# Car Fuel & Maintenance

A Nextcloud app for tracking fuel fill-ups and maintenance work across as many
cars as you own. Log every fill-up with the odometer reading and quantity,
mark it "full tank" when relevant, and consumption/cost are computed
automatically. Log maintenance work (services, repairs, inspections) with an
optional "next due" date or odometer reading to get a reminder as it
approaches or becomes overdue.

## Features

- **Multiple cars** — track as many vehicles as you like, each with its own
  history.
- **Fuel log** — date, odometer, quantity, unit (L / gal / kWh), price per
  unit or total cost (the other is computed automatically), station, notes.
- **Fill-to-fill consumption** — average consumption (per 100 distance units
  and distance per fuel unit) is computed between consecutive full-tank
  fill-ups; partial fills in between are folded into the next full one, so
  the numbers stay accurate.
- **Maintenance log** — date, type, odometer, description, cost, workshop,
  notes.
- **Due reminders** — set a next-due date and/or odometer on a maintenance
  entry to see it flagged as upcoming, due soon, or overdue on the car's
  Overview tab.
- **Spend tracking** — total fuel spend, maintenance spend, and cost per
  distance unit, per car.
- **Token-friendly API** — every action is also available over Nextcloud's OCS
  API with app-password auth, so a script or an AI assistant can log entries
  for you (see below).

## Requirements

- Nextcloud 34 (Hub 26 "Spring")
- PHP 8.2 – 8.5
- Node.js ^24 / npm ^11.3 (build only)

## Install into a Nextcloud instance

```bash
# from this directory
composer install --no-dev
npm install
npm run build

# then either symlink or copy this whole directory into your Nextcloud's
# apps/ (or custom_apps/) folder as "carfuelmaintance", then:
php occ app:enable carfuelmaintance
```

Database tables are created automatically on `app:enable` via the migration
in `lib/Migration/`. No manual SQL required.

## How it works, in short

Add a car with its starting odometer reading. Every fill-up you log after
that is compared against the previous full-tank fill-up: the distance covered
divided by the fuel used since then gives the consumption for that interval.
Partial fills (e.g. topping up without filling completely) are simply added
to the quantity of the next full-tank fill-up, so they don't skew the numbers.
Maintenance entries are independent of fuel entries and support optional due
reminders based on date, odometer, or both.

## Development

```bash
composer install
npm install
npm run watch     # rebuilds src/ on change

# run PHP unit tests (must be run from apps/carfuelmaintance inside a real
# Nextcloud server checkout — the app framework's tests/bootstrap.php needs it)
composer test:unit
```

See `lib/Service/StatsService.php` for the fill-to-fill consumption algorithm
and the maintenance-reminder logic. Nothing derived (consumption, totals,
reminders) is persisted — it's all computed at request time from the car's
fuel/maintenance entries.

## Translations

UI strings are wrapped in `t('carfuelmaintance', '...')` (JS) and ship with
`en` (source), `pt_PT`, `fr`, `es`, `de`, `it` and `nl` translations in `l10n/`.
To add another language, add a matching `l10n/<lang>.json` + `l10n/<lang>.js`
pair — the server discovers them automatically, no registration needed.
Nextcloud's `l10n/<lang>.json` format:

```json
{ "translations": { "Source string": "Translated string" }, "pluralForm": "nplurals=2; plural=(n != 1);" }
```

The matching `.js` file wraps the same data in
`OC.L10N.register("carfuelmaintance", {...}, "pluralForm...")` for the
browser side.

## Giving an AI assistant (or any external tool) token access

The app exposes every operation twice:

- **`/apps/carfuelmaintance/api/...`** — used by the bundled web UI,
  authenticated via your normal Nextcloud session + CSRF token.
- **`/ocs/v2.php/apps/carfuelmaintance/api/v1/...`** — the same operations,
  meant for external/automated clients (scripts, an AI agent pairing with
  you, a mobile shortcut, etc.), authenticated with a Nextcloud **app
  password** instead of a browser session.

To let something else act on your cars "in pair" with you:

1. In Nextcloud, go to **Settings → Security → Devices & sessions → Create new
   app password**. Give it a name (e.g. "AI pairing") and copy the generated
   username + password — this is the token; it's shown only once.
2. Give that username/password pair to your AI tool/script. It authenticates
   with plain **HTTP Basic Auth** and must send the `OCS-APIRequest: true`
   header on every request (standard Nextcloud OCS API requirement).

Example — list cars and log a fill-up, from the command line:

```bash
NC_USER="ricardo"
NC_TOKEN="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"   # the app password
BASE="https://your-nextcloud.example.com/ocs/v2.php/apps/carfuelmaintance/api/v1"

curl -s -u "$NC_USER:$NC_TOKEN" -H "OCS-APIRequest: true" -H "Accept: application/json" \
  "$BASE/cars"

curl -s -u "$NC_USER:$NC_TOKEN" -H "OCS-APIRequest: true" -H "Accept: application/json" \
  -X POST "$BASE/cars/1/fuel" \
  -d entryDate="2026-08-25" -d odometer=45210 -d quantity=38.5 -d totalCost=61.90
```

You can revoke access at any time from the same Security settings page by
deleting that app password — no code changes needed.

### Helping an AI discover the API instead of guessing endpoints

Point it at these two, in order, before it starts making requests:

1. **`GET /ocs/v2.php/apps/carfuelmaintance/api/v1/help`** — human/AI-readable
   quick reference (no authentication required). Lists every endpoint with
   its method and a one-line summary, and explains how to authenticate.
2. **`https://your-nextcloud.example.com/custom_apps/carfuelmaintance/openapi.json`**
   — the full OpenAPI 3.0 spec (parameters, request/response shapes). Served
   from the app's own folder, **not** the Nextcloud root.

See `lib/Controller/ApiController.php` for the full list of endpoints (cars,
fuel entries, maintenance entries).

## License

AGPL-3.0-or-later

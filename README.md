# Terapi

Healthcare integration hub built with Laravel 12 + Livewire 4 + Volt. Connects SIMRS (Hospital Information System) with SatuSehat (Kemkes FHIR), BPJS Kesehatan, TTE (Digital Signature), DICOM/PACS, and WhatsApp Gateway.

## Stack

- **PHP 8.2+**, Laravel 12, Livewire 4.1, Volt 1.7
- **Flux UI** — component library via Livewire Flux
- **Vite 6 + Tailwind CSS 4**
- **Database**: SQLite (dev) / MySQL (prod) + SIMRS external connection

## Requirements

- PHP 8.2+
- Composer
- Node.js 20+
- MySQL / SQLite

## Getting Started

```bash
# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations & seeders
php artisan migrate --seed

# Start all development services (server, queue, log stream, vite)
composer run dev
```

## Commands

```bash
# Development
composer run dev          # Start all services concurrently

# Build
npm run build             # Production asset build

# Testing
composer run test         # Run full test suite
php artisan test          # Equivalent
php artisan test --filter=NamaTest        # Run a single test
php artisan test tests/Feature/           # Run a suite
```

## Architecture

### Multiple Databases

```php
// Query SIMRS (read-only, external hospital system)
Model::on('simrs')->where(...)->get();
```

Two connections are defined in `config/database.php`:
- `sqlite`/`mysql` → main application database
- `simrs` → external SIMRS database (`DB_SIMRS_*` env vars)

> Do not create direct Eloquent relations between the two connections. Do joins/merges in PHP.

### Volt Pages

All pages are **Volt full-stack components** in `resources/views/pages/`:

```php
<?php
use Livewire\Attributes\{Layout, Title};

new #[Layout('layouts::app')] #[Title('Page Title')] class extends Component {
    public function with(): array {
        return ['data' => Model::all()];
    }
};
?>
{{-- Blade template below the PHP block --}}
```

### Configuration (3 Layers)

Priority: **Database > .env > config/*.php**

Use `ConfigurationHelper::get/set('key', value)` for all configuration changeable via UI (credentials, URLs, toggles). Values are cached in-memory with a 5-minute TTL from the `configurations` table.

### Integrated Services

| Service | Location | Integration |
|---------|----------|-------------|
| SatuSehat FHIR | `app/Services/SatuSehat/` | Kemkes FHIR R4 API |
| BPJS | `app/Services/Bpjs/` | vClaim, Antrian, ERM, ApoTek |
| DICOM/PACS | `app/Services/Dicom/` | Orthanc server |
| TTE | `app/Services/TteService` | Snowstorm digital signature |
| WhatsApp | `app/Services/WahaService`, `GowaService` | Waha & Gowa providers |
| RS Online | `app/Services/RsOnline/` | RS Online API |
| AI | `app/Services/AiService` | LLM prompting |

### API

Base path: `/api/v1`  
Auth: `x-token` header  
Scope per endpoint: `hospital`, `simrs`, `tte`, `dicom`, `whatsapp-gateway`, `qrcode`, `ai`

Middleware stack: `ApiTokenAuth → ApiScopeCheck → LimitRequestSize → ApiRequestLogger`

### Queue Jobs

All sync operations run async via queue:
- `SyncBatch*Job` — sync patients/practitioners from SIMRS to SatuSehat/BPJS
- `SendSatuSehatBundleJob` — send FHIR bundle
- `Import*Job` — import terminology (ICD, LOINC, SNOMED, HL7 CodeSystem)
- `Send*MessageJob` — send WhatsApp messages

Queue driver: database (default). Queue name: `sync` for sync jobs.

### Routing

```
routes/web.php   → authenticated web routes (Volt pages)
routes/api.php   → /api/v1/* with token auth
routes/auth.php  → login/logout
```

Sidebar navigation is configured via `config/sidebar.php`.

## Testing

PHPUnit 11 with SQLite in-memory. Config in `phpunit.xml`.

## License

Proprietary. All rights reserved.

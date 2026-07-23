# HLStatsX Community Edition — Laravel 13 Rebase

A full rebase of HLStatsX:CE to **Laravel 13**, replacing the legacy PHP 5 web frontend with a modern stack while keeping the same MySQL schema.

> **Perl daemon:** the Perl scripts used are from [SnipeZilla/HLSTATS-2](https://github.com/SnipeZilla/HLSTATS-2).

## Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 (PHP 8.5+) |
| Frontend | Blade + Vite + TailwindCSS v4 + Alpine.js |
| Charts | Chart.js |
| Maps | Leaflet.js |
| Auth | Laravel Breeze (admin guard on `hlstats_Admins`) |
| Cache | Redis (predis) |
| Queue | database |
| GeoIP | MaxMind GeoLite2 (geoip2/geoip2) |

## Requirements

- PHP 8.2+, Composer, Node.js 18+
- MySQL 5.7+ (existing HLStatsX database)
- Redis

## Installation

```bash
cd web/laravel
composer install
npm install
cp .env.example .env
php artisan key:generate
# Edit .env with your DB, Redis, Steam API key...
npm run build
php artisan migrate

# Required: seals the unauthenticated /install/* wizard, which can otherwise
# create a superadmin account and rewrite your DB credentials in .env.
echo "APP_INSTALLED=true" >> .env
php artisan config:clear
```

## Key .env Variables

```dotenv
DB_DATABASE=hlstats
REDIS_CLIENT=predis
STEAM_API_KEY=
GEOIP_DB_PATH=/path/to/GeoLite2-Country.mmdb
GEOIP_CITY_DB_PATH=/path/to/GeoLite2-City.mmdb
HLSTATS_SITE_NAME="HLstatsX | My Community"
HLSTATS_HISTORY_DAYS=28
```

## Scheduler

```cron
* * * * * cd /path/to/web/laravel && php artisan schedule:run >> /dev/null 2>&1
```

## Artisan Commands

| Command | Schedule |
|---|---|
| `hlstats:check-servers` | Every 5 min |
| `hlstats:steam-sync` | Hourly |
| `hlstats:compute-awards` | Daily 00:05 |
| `hlstats:prune-events` | Weekly |
| `hlstats:update-geoip` | Monthly |

## Themes

6 built-in themes: `hlstatsx-dark`, `hlstatsx-classic`, `midnight-blue`, `carbon`, `neon-green`, `arctic-light`. Managed via **Admin → Themes**. All colors use CSS custom properties — no hardcoded values.

## Admin Panel

Access at `/admin`. Manages players, clans, servers, games, weapons, bans, and themes.

## Legacy URL Support

`/hlstats.php?mode=players&game=cstrike` → `/players?game=cstrike`

## Database Note

This project **never modifies** the HLStatsX schema. All `hlstats_*` tables and column names are used as-is.

---

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

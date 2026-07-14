# HTTP Health Monitoring

[English](./README.md) | [中文](./README_ZH.md)

An automated HTTP URL health monitoring platform built with **Laravel 13 + Prometheus + Grafana**. Periodically checks configured URLs, collects HTTP status codes, response latency, and SSL certificate expiry data as Prometheus metrics, visualizes everything through a pre-configured Grafana dashboard with multi-tier alerting via DingTalk and email.

## Architecture

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Laravel Nova │────▶│  Scheduler   │────▶│  UrlCheckJob │
│  /admin       │     │  (every 1m)  │     │  (queued)    │
└──────────────┘     └──────────────┘     └──────┬───────┘
                                                 │
                                    ┌────────────┼────────────┐
                                    ▼            ▼            ▼
                              HTTP GET       Latency      SSL Cert
                              Status Code    Measure      Check
                                    │            │            │
                                    ▼            ▼            ▼
                           ┌────────────────────────────────────┐
                           │     Prometheus (Redis Storage)      │
                           └──────────────────┬─────────────────┘
                                              │ scrape :8000/metrics
                                              ▼
                           ┌────────────────────────────────────┐
                           │         Grafana Dashboard          │
                           │   6 Panels + Alerting Rules        │
                           └──────────┬──────────────┬──────────┘
                                      │              │
                                 DingTalk          Email
                               (all alerts)    (critical only)
```

## Features

- **URL Monitoring** -- Register URLs via Laravel Nova admin panel; active URLs are checked every minute via queued jobs
- **Prometheus Metrics** -- HTTP status codes, response latency, and SSL certificate expiry stored in Redis
- **Grafana Dashboard** -- Pre-configured with 6 visualization panels (success rate, latency trends, error ranking, status distribution, SSL health)
- **Multi-tier Alerting** -- SSL expiry alerts with 3 urgency levels, HTTP error rate alerts, with DingTalk + email notifications
- **Non-working Hours Mute** -- Critical alerts are silenced during weekends and off-hours (00:00-09:00, 20:00-24:00)
- **Dockerized Stack** -- One-command startup with 7 services

## Tech Stack

### Backend

| Package | Version |
|---------|---------|
| PHP | 8.4 |
| Laravel | 13.19 |
| Laravel Nova | 5.9 |
| Laravel Fortify | 1.37 |
| Prometheus PHP Client | 2.15 |

### Frontend

| Package | Version |
|---------|---------|
| Vue | 3.5 |
| Inertia.js | 3.6 |
| Tailwind CSS | 4.3 |
| Vite | 8.0 |
| TypeScript | 5.2 |

### Monitoring & Infrastructure

| Service | Purpose |
|---------|---------|
| Prometheus | Time-series metrics collection (scrape every 15s) |
| Grafana | Dashboard visualization & alerting |
| MySQL 8.0 | Primary database |
| Redis 7 | Queue driver + Prometheus metrics storage |
| Docker Compose | 7-service containerized deployment |

## Quick Start

### Using Docker (Recommended)

```bash
git clone https://github.com/your-username/http-health-monitoring.git
cd http-health-monitoring
docker compose up -d
```

Once running, access:

| Service | URL | Credentials |
|---------|-----|-------------|
| **Nova Admin** | http://localhost:8000/admin | (register a new account) |
| **Grafana** | http://localhost:3000 | admin / 111111 |
| **Prometheus** | http://localhost:9090 | -- |
| **Metrics Endpoint** | http://localhost:8000/metrics | -- |

### Without Docker

```bash
git clone https://github.com/your-username/http-health-monitoring.git
cd http-health-monitoring
composer setup
php artisan serve
```

In a separate terminal:

```bash
php artisan queue:work redis
php artisan schedule:work
```

## Docker Services

| Service | Image | Port | Description |
|---------|-------|------|-------------|
| `app` | Custom (PHP 8.5 CLI) | 8000 | Laravel application server |
| `worker` | Custom (PHP 8.5 CLI) | -- | Redis queue worker |
| `scheduler` | Custom (PHP 8.5 CLI) | -- | Task scheduler (dispatches URL checks every minute) |
| `mysql` | mysql:8.0 | 3306 (internal) | Primary database |
| `redis` | redis:7-alpine | 6379 (internal) | Queue + Prometheus storage backend |
| `prometheus` | prom/prometheus:latest | 9090 | Metrics collection (15s scrape interval) |
| `grafana` | grafana/grafana:latest | 3000 | Dashboards + alerting |

## Prometheus Metrics

Three metric types are collected per monitored URL:

| Metric | Type | Labels | Description |
|--------|------|--------|-------------|
| `laravel_monitor_url_requests_total` | Counter | url, name, status | Total HTTP request count |
| `laravel_monitor_url_request_latency_seconds` | Gauge | url, name | Request response latency |
| `laravel_monitor_ssl_certificate_expiry_days` | Gauge | url, name | Days until SSL certificate expires |

Metrics are stored in Redis and exposed at `/metrics` in Prometheus text format.

## Grafana Dashboard

The pre-provisioned dashboard (`laravel_url_monitor`) includes 6 panels:

| Panel | Type | Description |
|-------|------|-------------|
| Overall HTTP 200 Success Rate | Stat | Thresholds: red < 95%, green > 99% |
| Anomalous Request Ratio | Stat | Thresholds: green < 1%, red > 5% |
| Per-site Latency Trend | Time Series | 5-second refresh |
| Per-site Error Rate Ranking | Table | Sorted descending |
| Per-site Status Code Distribution | Stacked Bar | Visual breakdown by status code |
| SSL Certificate Health | Bar Gauge | Red < 30d, yellow < 90d, green > 90d |

## Alerting

### Alert Rules

| Rule | Condition | Urgency | Channel | Frequency |
|------|-----------|---------|---------|-----------|
| SSL Expiry Warning | <= 30 days | Low | DingTalk | Weekly |
| SSL Expiry Alert | <= 20 days | Medium | DingTalk | Every 3 days |
| SSL Expiry Critical | <= 10 days | High | DingTalk + Email | Daily |
| HTTP Error Rate | > 5% | Critical | DingTalk + Email | Every 4 hours |

### Mute Schedule

Critical alerts are silenced during:
- Weekends (Saturday, Sunday)
- 00:00 -- 09:00
- 20:00 -- 24:00

## Project Structure

```
├── app/
│   ├── Http/Controllers/
│   │   └── MetricsController.php     # /metrics endpoint (Prometheus text format)
│   ├── Jobs/
│   │   └── UrlCheckJob.php           # Core monitoring job (HTTP check + SSL check + metrics)
│   ├── Models/
│   │   └── MonitoredUrl.php          # URL monitoring entity
│   └── Nova/
│       ├── MonitoredUrl.php          # Admin resource for URL management
│       └── User.php                  # Admin resource for user management
├── docker/
│   ├── grafana/provisioning/         # Grafana dashboards, datasources, alerts
│   ├── php/Dockerfile                # PHP 8.5 CLI image
│   └── prometheus/prometheus.yml     # Prometheus scrape configuration
├── database/migrations/              # Schema definitions
├── routes/
│   ├── console.php                   # Scheduler: dispatches UrlCheckJob every minute
│   └── web.php                       # Routes: / -> Nova, /metrics -> Prometheus
├── resources/js/                     # Vue 3 + Inertia.js frontend
├── tests/                            # Pest 4 + PHPUnit 12 tests
└── docker-compose.yml                # 7-service stack definition
```

## Development

### Prerequisites

- PHP 8.3+
- Node.js 22+
- Composer 2
- Docker & Docker Compose (for containerized setup)

### Available Commands

```bash
# Setup everything (install deps, generate key, migrate, build assets)
composer setup

# Start all dev services (Laravel + Vite + Queue + Scheduler)
composer dev

# Run tests
composer test

# Lint PHP (Pint)
composer lint

# Lint frontend (ESLint + Prettier)
npm run lint
npm run format

# Static analysis (Larastan)
composer types:check
```

### Adding Monitored URLs

1. Open the Nova admin panel at `/admin`
2. Navigate to **Monitored URLs**
3. Click **Create Monitored URL**
4. Enter the name and URL, ensure **Is Active** is toggled on
5. The scheduler will begin checking the URL within 1 minute

## CI/CD

### GitHub Actions

| Workflow | Trigger | Description |
|----------|---------|-------------|
| **tests** | Push/PR to develop, main, master | PHP matrix (8.3/8.4/8.5), runs Larastan + Pest tests |
| **lint** | Push/PR to develop, main, master | Pint (PHP) + Prettier + ESLint (frontend) |
| **Dependabot** | Weekly | Monitors GitHub Actions dependencies |

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

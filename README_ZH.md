# HTTP 健康监控平台

[English](./README.md) | [中文](./README_ZH.md)

一个基于 **Laravel 13 + Prometheus + Grafana** 的自动化 HTTP URL 健康监控平台。定时检查已配置的 URL，采集 HTTP 状态码、响应延迟和 SSL 证书到期时间等 Prometheus 指标，通过预配置的 Grafana 仪表盘进行可视化展示，并支持通过钉钉和邮件进行多级告警通知。

## 架构

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Laravel Nova │────▶│  Scheduler   │────▶│  UrlCheckJob │
│  /admin       │     │  (每分钟)    │     │  (队列任务)  │
└──────────────┘     └──────────────┘     └──────┬───────┘
                                                 │
                                    ┌────────────┼────────────┐
                                    ▼            ▼            ▼
                               HTTP GET       延迟        SSL 证书
                               状态码         测量         检测
                                    │            │            │
                                    ▼            ▼            ▼
                           ┌────────────────────────────────────┐
                           │      Prometheus (Redis 存储)       │
                           └──────────────────┬─────────────────┘
                                              │ 抓取 :8000/metrics
                                              ▼
                           ┌────────────────────────────────────┐
                           │         Grafana 仪表盘              │
                           │    6 个面板 + 告警规则              │
                           └──────────┬──────────────┬──────────┘
                                      │              │
                                 钉钉通知          邮件通知
                                (所有告警)      (仅严重告警)
```

## 核心功能

- **URL 监控** -- 通过 Laravel Nova 管理面板注册监控 URL，每分钟通过队列任务自动检查
- **Prometheus 指标** -- HTTP 状态码、响应延迟、SSL 证书到期时间存储在 Redis 中
- **Grafana 仪表盘** -- 预配置 6 个可视化面板（成功率、延迟趋势、错误排名、状态分布、SSL 健康）
- **多级告警** -- SSL 过期告警分 3 个紧急程度，HTTP 错误率告警，支持钉钉 + 邮件通知
- **非工作时间静默** -- 严重告警在周末和非工作时段（00:00-09:00、20:00-24:00）自动静默
- **Docker 化部署** -- 一条命令启动 7 个服务

## 技术栈

### 后端

| 包名 | 版本 |
|------|------|
| PHP | 8.4 |
| Laravel | 13.19 |
| Laravel Nova | 5.9 |
| Laravel Fortify | 1.37 |
| Prometheus PHP Client | 2.15 |

### 前端

| 包名 | 版本 |
|------|------|
| Vue | 3.5 |
| Inertia.js | 3.6 |
| Tailwind CSS | 4.3 |
| Vite | 8.0 |
| TypeScript | 5.2 |

### 监控与基础设施

| 服务 | 用途 |
|------|------|
| Prometheus | 时序指标采集（每 15 秒抓取） |
| Grafana | 仪表盘可视化与告警 |
| MySQL 8.0 | 主数据库 |
| Redis 7 | 队列驱动 + Prometheus 指标存储 |
| Docker Compose | 7 服务容器化部署 |

## 快速开始

### 使用 Docker（推荐）

```bash
git clone https://github.com/your-username/http-health-monitoring.git
cd http-health-monitoring
docker compose up -d
```

启动后访问：

| 服务 | 地址 | 登录凭证 |
|------|------|----------|
| **Nova 管理后台** | http://localhost:8000/admin | （注册新账号） |
| **Grafana** | http://localhost:3000 | admin / 111111 |
| **Prometheus** | http://localhost:9090 | -- |
| **指标端点** | http://localhost:8000/metrics | -- |

### 不使用 Docker

```bash
git clone https://github.com/your-username/http-health-monitoring.git
cd http-health-monitoring
composer setup
php artisan serve
```

在另一个终端中：

```bash
php artisan queue:work redis
php artisan schedule:work
```

## Docker 服务

| 服务 | 镜像 | 端口 | 说明 |
|------|------|------|------|
| `app` | 自定义 (PHP 8.5 CLI) | 8000 | Laravel 应用服务器 |
| `worker` | 自定义 (PHP 8.5 CLI) | -- | Redis 队列消费者 |
| `scheduler` | 自定义 (PHP 8.5 CLI) | -- | 任务调度器（每分钟分发 URL 检查任务） |
| `mysql` | mysql:8.0 | 3306 (内部) | 主数据库 |
| `redis` | redis:7-alpine | 6379 (内部) | 队列 + Prometheus 存储后端 |
| `prometheus` | prom/prometheus:latest | 9090 | 指标采集（15 秒抓取间隔） |
| `grafana` | grafana/grafana:latest | 3000 | 仪表盘 + 告警 |

## Prometheus 指标

每个被监控的 URL 采集三个指标：

| 指标 | 类型 | 标签 | 说明 |
|------|------|------|------|
| `laravel_monitor_url_requests_total` | Counter | url, name, status | HTTP 请求总数 |
| `laravel_monitor_url_request_latency_seconds` | Gauge | url, name | 请求响应延迟 |
| `laravel_monitor_ssl_certificate_expiry_days` | Gauge | url, name | SSL 证书剩余天数 |

指标存储在 Redis 中，通过 `/metrics` 端点以 Prometheus 文本格式暴露。

## Grafana 仪表盘

预配置的仪表盘（`laravel_url_monitor`）包含 6 个面板：

| 面板 | 类型 | 说明 |
|------|------|------|
| HTTP 200 总成功率 | Stat | 阈值：红色 < 95%，绿色 > 99% |
| 异常请求比例 | Stat | 阈值：绿色 < 1%，红色 > 5% |
| 各站点延迟趋势 | Time Series | 5 秒刷新 |
| 各站点错误率排名 | Table | 降序排列 |
| 各站点状态码分布 | Stacked Bar | 按状态码可视化分布 |
| SSL 证书健康度 | Bar Gauge | 红色 < 30 天，黄色 < 90 天，绿色 > 90 天 |

## 告警配置

### 告警规则

| 规则 | 条件 | 紧急程度 | 通知渠道 | 频率 |
|------|------|----------|----------|------|
| SSL 过期预警 | <= 30 天 | 低 | 钉钉 | 每周一次 |
| SSL 过期警告 | <= 20 天 | 中 | 钉钉 | 每 3 天一次 |
| SSL 过期严重 | <= 10 天 | 高 | 钉钉 + 邮件 | 每天一次 |
| HTTP 错误率 | > 5% | 严重 | 钉钉 + 邮件 | 每 4 小时一次 |

### 静默时段

严重告警在以下时段自动静默：
- 周末（周六、周日）
- 00:00 -- 09:00
- 20:00 -- 24:00

## 项目结构

```
├── app/
│   ├── Http/Controllers/
│   │   └── MetricsController.php     # /metrics 端点（Prometheus 文本格式）
│   ├── Jobs/
│   │   └── UrlCheckJob.php           # 核心监控任务（HTTP 检测 + SSL 检测 + 指标上报）
│   ├── Models/
│   │   └── MonitoredUrl.php          # URL 监控实体
│   └── Nova/
│       ├── MonitoredUrl.php          # URL 管理后台资源
│       └── User.php                  # 用户管理后台资源
├── docker/
│   ├── grafana/provisioning/         # Grafana 仪表盘、数据源、告警配置
│   ├── php/Dockerfile                # PHP 8.5 CLI 镜像
│   └── prometheus/prometheus.yml     # Prometheus 抓取配置
├── database/migrations/              # 数据库迁移定义
├── routes/
│   ├── console.php                   # 调度器：每分钟分发 UrlCheckJob
│   └── web.php                       # 路由：/ -> Nova，/metrics -> Prometheus
├── resources/js/                     # Vue 3 + Inertia.js 前端
├── tests/                            # Pest 4 + PHPUnit 12 测试
└── docker-compose.yml                # 7 服务栈定义
```

## 开发指南

### 环境要求

- PHP 8.3+
- Node.js 22+
- Composer 2
- Docker & Docker Compose（容器化部署）

### 常用命令

```bash
# 一键安装（依赖安装、密钥生成、数据库迁移、前端构建）
composer setup

# 启动所有开发服务（Laravel + Vite + 队列 + 调度器）
composer dev

# 运行测试
composer test

# PHP 代码风格检查（Pint）
composer lint

# 前端代码检查（ESLint + Prettier）
npm run lint
npm run format

# 静态分析（Larastan）
composer types:check
```

### 添加监控 URL

1. 打开 Nova 管理后台 `/admin`
2. 导航到 **Monitored URLs**
3. 点击 **Create Monitored URL**
4. 输入名称和 URL，确保 **Is Active** 开关已打开
5. 调度器将在 1 分钟内开始检查该 URL

## CI/CD

### GitHub Actions

| 工作流 | 触发条件 | 说明 |
|--------|----------|------|
| **tests** | Push/PR 到 develop, main, master | PHP 矩阵测试（8.3/8.4/8.5），运行 Larastan + Pest |
| **lint** | Push/PR 到 develop, main, master | Pint（PHP）+ Prettier + ESLint（前端） |
| **Dependabot** | 每周 | 监控 GitHub Actions 依赖更新 |

## 开源协议

Laravel 框架基于 [MIT 协议](https://opensource.org/licenses/MIT) 开源。

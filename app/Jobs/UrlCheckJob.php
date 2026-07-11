<?php

namespace App\Jobs;

use App\Models\MonitoredUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class UrlCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected MonitoredUrl $monitoredUrl) {}

    public function handle(): void
    {
        if (!$this->monitoredUrl->is_active) {
            return;
        }

        $url = $this->monitoredUrl->url;
        $labels = [$url, $this->monitoredUrl->name];

        // 连接到 Redis 存储指标
        Redis::setDefaultOptions(['host' => env('REDIS_HOST', '127.0.0.1')]);
        $registry = new CollectorRegistry(new Redis());

        $counter = $registry->getOrRegisterCounter('laravel_monitor', 'url_requests_total', '请求总数', ['url', 'name', 'status']);
        $gaugeLatency = $registry->getOrRegisterGauge('laravel_monitor', 'url_request_latency_seconds', '请求延迟时间', ['url', 'name']);
        $gaugeSsl = $registry->getOrRegisterGauge('laravel_monitor', 'ssl_certificate_expiry_days', 'SSL证书剩余天数', ['url', 'name']);

        $start = microtime(true);
        try {
            $response = Http::timeout(10)->get($url);
            $statusCode = $response->status();
        } catch (\Exception $e) {
            $statusCode = 500;
        }
        $latency = microtime(true) - $start;

        $counter->inc(array_merge($labels, [(string)$statusCode]));
        $gaugeLatency->set($latency, $labels);

        $sslDays = $this->getSslCertificateExpiryDays($url);
        if ($sslDays !== null) {
            $gaugeSsl->set($sslDays, $labels);
        }
    }

    protected function getSslCertificateExpiryDays(string $url): ?int
    {
        try {
            $host = parse_url($url, PHP_URL_HOST);
            if (!$host) return null;

            $context = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
            $client = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

            if (!$client) return null;

            $params = stream_context_get_params($client);
            $cert = $params["options"]["ssl"]["peer_certificate"];
            $certInfo = openssl_x509_parse($cert);

            if (isset($certInfo['validTo_time_t'])) {
                $validTo = $certInfo['validTo_time_t'];
                return (int) ceil(($validTo - time()) / 86400);
            }
        } catch (\Exception $e) {
            // 忽略非 HTTPS 或证书解析错误
        }
        return null;
    }
}
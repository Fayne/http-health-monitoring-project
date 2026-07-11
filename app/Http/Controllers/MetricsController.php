<?php

namespace App\Http\Controllers;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Redis;

class MetricsController extends Controller
{
    public function __invoke()
    {
        Redis::setDefaultOptions(['host' => env('REDIS_HOST', '127.0.0.1')]);
        $registry = new CollectorRegistry(new Redis());

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return response($result, 200)
            ->header('Content-Type', RenderTextFormat::MIME_TYPE);
    }
}
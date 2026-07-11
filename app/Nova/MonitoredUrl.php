<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class MonitoredUrl extends Resource
{
    public static $model = \App\Models\MonitoredUrl::class;
    public static $title = 'name';
    public static $search = ['id', 'name', 'url'];

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            Text::make('站点名称', 'name')->rules('required', 'max:255'),
            Text::make('目标 URL', 'url')->rules('required', 'url', 'max:255'),
            Boolean::make('是否启用', 'is_active')->default(true),
        ];
    }
}
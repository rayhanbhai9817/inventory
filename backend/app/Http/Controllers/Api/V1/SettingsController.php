<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Services\AuditLogger;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function show(Request $request): JsonResponse
    {
        $business = $request->user()->business;
        $settings = BusinessSetting::firstOrCreate(['business_id' => Tenant::id()]);

        return response()->json([
            'data' => [
                'general' => [
                    'company_name' => $business->name,
                    'logo_path' => $business->logo_path,
                    'timezone' => $business->timezone,
                    'date_format' => $settings->date_format,
                    'number_format' => $settings->number_format,
                ],
                'inventory' => [
                    'default_min_stock_threshold' => $settings->default_min_stock_threshold,
                ],
                'notifications' => [
                    'low_stock_notifications_enabled' => $settings->low_stock_notifications_enabled,
                    'out_of_stock_notifications_enabled' => $settings->out_of_stock_notifications_enabled,
                ],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'max:100'],
            'date_format' => ['sometimes', 'string', 'max:50'],
            'number_format' => ['sometimes', 'string', 'max:50'],
            'default_min_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'low_stock_notifications_enabled' => ['sometimes', 'boolean'],
            'out_of_stock_notifications_enabled' => ['sometimes', 'boolean'],
        ]);

        $business = $request->user()->business;
        if (isset($data['company_name']) || isset($data['timezone'])) {
            $business->update(array_filter([
                'name' => $data['company_name'] ?? null,
                'timezone' => $data['timezone'] ?? null,
            ]));
        }

        $settings = BusinessSetting::firstOrCreate(['business_id' => Tenant::id()]);
        $settings->update(array_intersect_key($data, array_flip([
            'date_format', 'number_format', 'default_min_stock_threshold',
            'low_stock_notifications_enabled', 'out_of_stock_notifications_enabled',
        ])));

        $this->auditLogger->log('settings_updated', $settings, null, $data, $request->user());

        return $this->show($request);
    }
}

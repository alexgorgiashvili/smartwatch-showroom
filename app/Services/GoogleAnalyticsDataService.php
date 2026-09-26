<?php

namespace App\Services;

use Google\Client;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\DateRange;
use Google\Service\AnalyticsData\Metric;
use Google\Service\AnalyticsData\RunReportRequest;
use Illuminate\Support\Facades\Log;

/** GA4 Data API reader. It only requests analytics.readonly data. */
class GoogleAnalyticsDataService
{
    public function summary(int $days, ?string $utmCampaign = null): ?array
    {
        if (!class_exists(Client::class) || !class_exists(AnalyticsData::class)) return null;
        $path = config('services.google.analytics_credentials');
        $property = config('services.google.analytics_property_id');
        if (!$path || !$property || !is_file($path)) return null;
        try {
            $client = new Client();
            $client->setAuthConfig($path);
            $client->addScope('https://www.googleapis.com/auth/analytics.readonly');
            $service = new AnalyticsData($client);
            $request = new RunReportRequest([
                'dateRanges' => [new DateRange(['startDate' => $days.'daysAgo', 'endDate' => 'today'])],
                'metrics' => [new Metric(['name' => 'sessions']), new Metric(['name' => 'totalUsers']), new Metric(['name' => 'conversions'])],
            ]);
            // UTM-specific filtering can be added only once a verified campaign id is supplied by the intake client.
            $response = $service->properties->runReport('properties/'.$property, $request);
            $values = $response->getRows()[0]?->getMetricValues() ?? [];
            return ['sessions' => (int) ($values[0]?->getValue() ?? 0), 'users' => (int) ($values[1]?->getValue() ?? 0), 'conversions' => (int) ($values[2]?->getValue() ?? 0), 'utm_campaign' => $utmCampaign];
        } catch (\Throwable $e) {
            Log::warning('GA4 summary request failed.');
            return null;
        }
    }
}

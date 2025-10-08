<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Holiday;

class UpstoxHolidayService
{
    protected $apiUrl = 'https://api.upstox.com/v2/market/holidays';

    public function fetchAndStore()
    {
        $response = Http::withToken(config('services.upstox.access_token'))->get($this->apiUrl);
        $dataNode = $response->json('data');

        $total = 0;

        if ($dataNode && is_array($dataNode)) {
            foreach ($dataNode as $holiday) {
                // Handle closed exchanges
                if (!empty($holiday['closed_exchanges']) && is_array($holiday['closed_exchanges'])) {
                    foreach ($holiday['closed_exchanges'] as $exchange) {
                        Holiday::updateOrCreate(
                            [
                                'date' => $holiday['date'] ?? null,
                                'exchange' => $exchange,
                            ],
                            [
                                'date' => $holiday['date'] ?? null,
                                'exchange' => $exchange,
                                'holiday_type' => $holiday['holiday_type'] ?? null,
                                'description' => $holiday['description'] ?? null,
                            ]
                        );
                        $total++;
                    }
                }
                // Handle open exchanges
                if (!empty($holiday['open_exchanges']) && is_array($holiday['open_exchanges'])) {
                    foreach ($holiday['open_exchanges'] as $e) {
                        Holiday::updateOrCreate(
                            [
                                'date' => $holiday['date'] ?? null,
                                'exchange' => $e['exchange'] ?? null,
                            ],
                            [
                                'date' => $holiday['date'] ?? null,
                                'exchange' => $e['exchange'] ?? null,
                                'holiday_type' => $holiday['holiday_type'] ?? null,
                                'description' => $holiday['description'] ?? null,
                                // Optionally add start_time, end_time (if you add columns)
                            ]
                        );
                        $total++;
                    }
                }
                // If both arrays empty, optionally record a special holiday for "ALL"
                if (empty($holiday['closed_exchanges']) && empty($holiday['open_exchanges'])) {
                    Holiday::updateOrCreate(
                        [
                            'date' => $holiday['date'] ?? null,
                            'exchange' => 'ALL',
                        ],
                        [
                            'date' => $holiday['date'] ?? null,
                            'exchange' => 'ALL',
                            'holiday_type' => $holiday['holiday_type'] ?? null,
                            'description' => $holiday['description'] ?? null,
                        ]
                    );
                    $total++;
                }
            }
        }
        return $total;
    }

}



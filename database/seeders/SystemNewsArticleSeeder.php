<?php

namespace Database\Seeders;

use App\Models\SystemNewsArticle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemNewsArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing articles
        SystemNewsArticle::query()->delete();

        $articles = [
            [
                'title' => 'New Real-Time Tracking Feature Launched',
                'category' => 'Feature Release',
                'excerpt' => 'Real-time vehicle tracking is now available across all regions with improved accuracy and lower latency.',
                'body' => 'Our latest update introduces real-time vehicle tracking capabilities with enhanced GPS accuracy and lower latency. Drivers and passengers can now see live location updates every 5 seconds.',
                'icon' => 'heroicon-o-map-pin',
                'color' => 'success',
                'is_published' => true,
                'published_at' => now()->subHours(12),
                'published_by' => 1,
            ],
            [
                'title' => 'Scheduled Maintenance on April 10th',
                'category' => 'Maintenance',
                'excerpt' => 'Services will be temporarily unavailable for 2 hours on April 10th from 2:00 AM to 4:00 AM UTC for system upgrades.',
                'body' => 'We will be performing scheduled maintenance on our servers to improve performance and security. The service will be unavailable during this period.',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'color' => 'warning',
                'is_published' => true,
                'published_at' => now()->subDays(1)->setHour(14),
                'published_by' => 1,
            ],
            [
                'title' => 'Driver Earnings Dashboard Improvements',
                'category' => 'Update',
                'excerpt' => 'Enhanced earnings analytics with trip-based aggregation for better accuracy and real-time data visibility.',
                'body' => 'We have upgraded the driver earnings dashboard with improved analytics, real-time data aggregation from completed trips, and platform commissions tracking.',
                'icon' => 'heroicon-o-trending-up',
                'color' => 'primary',
                'is_published' => true,
                'published_at' => now()->subDays(2)->setHour(9),
                'published_by' => 1,
            ],
            [
                'title' => 'New Safety Feature: Emergency Contact Alerts',
                'category' => 'Safety',
                'excerpt' => 'Drivers and passengers can now set emergency contacts who will receive automatic alerts during incidents.',
                'body' => 'Our new emergency alert system allows drivers and passengers to configure trusted contacts who will automatically receive location and status updates during emergencies.',
                'icon' => 'heroicon-o-bell-alert',
                'color' => 'danger',
                'is_published' => true,
                'published_at' => now()->subDays(3)->setHour(11),
                'published_by' => 1,
            ],
            [
                'title' => 'Q1 2026 Performance Report Released',
                'category' => 'Report',
                'excerpt' => 'Review our quarterly performance metrics, including growth, safety improvements, and operational efficiency gains.',
                'body' => 'Our Q1 2026 performance report shows outstanding growth with a 45% increase in ride completions, improved safety metrics, and enhanced operational efficiency across all regions.',
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'info',
                'is_published' => true,
                'published_at' => now()->subDays(7)->setHour(8),
                'published_by' => 1,
            ],
            [
                'title' => 'API Rate Limits Updated',
                'category' => 'Update',
                'excerpt' => 'We have increased API rate limits for partners to support higher throughput applications.',
                'body' => 'API rate limits have been updated to allow for higher throughput. Partners can now make up to 10,000 requests per hour with improved response times.',
                'icon' => 'heroicon-o-lightning-bolt',
                'color' => 'primary',
                'is_published' => true,
                'published_at' => now()->subDays(10)->setHour(15),
                'published_by' => 1,
            ],
            [
                'title' => 'Driver Verification Process Enhanced',
                'category' => 'Update',
                'excerpt' => 'Faster and more secure driver verification with advanced identity checks.',
                'body' => 'We have enhanced our driver verification process with advanced identity matching, background checks, and document verification to ensure platform safety.',
                'icon' => 'heroicon-o-shield-check',
                'color' => 'success',
                'is_published' => true,
                'published_at' => now()->subDays(14)->setHour(10),
                'published_by' => 1,
            ],
            [
                'title' => 'Passenger Safety Features Expanded',
                'category' => 'Safety',
                'excerpt' => 'New in-app safety tools including timeline sharing and ride monitoring.',
                'body' => 'Enhanced passenger safety features now include live timeline sharing with emergency contacts, ride monitoring, and automatic incident reporting.',
                'icon' => 'heroicon-o-shield-exclamation',
                'color' => 'danger',
                'is_published' => true,
                'published_at' => now()->subDays(18)->setHour(12),
                'published_by' => 1,
            ],
        ];

        foreach ($articles as $article) {
            SystemNewsArticle::create($article);
        }
    }
}


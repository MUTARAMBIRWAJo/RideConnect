<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-blue-600 to-cyan-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-blue-100">Stay Informed</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Platform News & Updates</h1>
            <p class="mt-2 max-w-2xl text-sm text-blue-100 sm:text-base">
                Latest announcements, feature releases, maintenance schedules, and important system updates.
            </p>
        </section>

        <!-- News Articles Grid -->
        <section class="space-y-3">
            @forelse ($this->getNewsArticles() as $article)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg bg-{{ $article['color'] }}-50 p-2">
                                    <svg class="h-5 w-5 text-{{ $article['color'] }}-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2m0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8m3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5m-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11m3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $article['title'] }}</h3>
                                    <p class="text-xs text-slate-500">{{ $article['category'] }} · {{ \Carbon\Carbon::parse($article['published_at'])->diffForHumans() }}</p>
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">{{ $article['excerpt'] }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-center">
                    <p class="text-sm text-slate-600">No news articles available at the moment.</p>
                </div>
            @endforelse
        </section>

        <!-- Subscribe Section -->
        <section class="rounded-xl border border-blue-200 bg-blue-50 p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-blue-900">Stay Updated</h3>
                    <p class="mt-1 text-xs text-blue-700">Subscribe to push notifications for critical system updates and announcements.</p>
                </div>
                <x-filament::button color="info" size="sm">Enable Notifications</x-filament::button>
            </div>
        </section>
    </div>
</x-filament-panels::page>

<?php

namespace App\Filament\Pages;

use App\Models\SystemNewsArticle;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class SystemNewsAndUpdates extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'News & Updates';

    protected static ?string $navigationGroup = 'Information Hub';

    protected static ?int $navigationSort = 200;

    protected static ?string $title = 'Platform News & Updates';

    protected static string $view = 'filament.pages.system-news-and-updates';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return true; // All authenticated users can access
    }

    public function getTitle(): string
    {
        return 'Platform News & Updates';
    }

    public static function getNavigationLabel(): string
    {
        return 'News & Updates';
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return 'heroicon-o-newspaper';
    }

    /** @return array<int, array<string, mixed>> */
    public function getNewsArticles(): array
    {
        // Fetch real articles from database, ordered by published date (newest first)
        $articles = SystemNewsArticle::query()
            ->published()
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (SystemNewsArticle $article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'category' => $article->category,
                    'excerpt' => $article->excerpt,
                    'published_at' => $article->published_at?->toDateTimeString() ?? now()->toDateTimeString(),
                    'icon' => $article->icon,
                    'color' => $article->color,
                ];
            })
            ->toArray();

        // If no articles exist in database, return empty array (blade will show "No news articles available")
        return $articles;
    }
}

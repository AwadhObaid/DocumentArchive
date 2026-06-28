<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Observers\DocumentObserver;
use App\Observers\DocumentAttachmentObserver;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Document::observe(DocumentObserver::class);
        DocumentAttachment::observe(DocumentAttachmentObserver::class);

        //
    }
}

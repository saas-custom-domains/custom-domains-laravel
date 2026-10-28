<?php

use App\Http\Controllers\CaddyAskController;
use App\Http\Controllers\SaasCustomDomainsWebhookController as Webhook;
use Illuminate\Support\Facades\Route;

// Called by machines on whatever hostname reaches the app (in Docker,
// webhooks arrive as Host: app:3000), so no host checks here.
Route::post('/webhooks/saascustomdomains', Webhook::class);
Route::get('/caddy/ask', CaddyAskController::class);

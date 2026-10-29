<?php

use App\Http\Controllers\SaasCustomDomainsWebhookController as Webhook;
use Illuminate\Support\Facades\Route;

// Called by SaaS Custom Domains on whatever hostname reaches the app
// (in Docker, webhooks arrive as Host: app:3000), so no host checks.
Route::post('/webhooks/saascustomdomains', Webhook::class);

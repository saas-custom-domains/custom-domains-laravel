<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One board per company: the tenant.
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline');
            $table->timestamps();
        });

        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')
                ->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('location');
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')
                ->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')
                ->constrained()->cascadeOnDelete();
            // Normalized (lowercase, no port, no trailing dot). Every
            // request on a customer domain looks it up by this column.
            $table->string('host')->unique();
            // pending | active
            $table->string('status')->default('pending');
            $table->boolean('https_ready')->default(false);
            $table->string('provider_uuid')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domains');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('boards');
    }
};

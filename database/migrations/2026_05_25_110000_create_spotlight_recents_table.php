<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spotlight_recents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_key');
            $table->string('result_id');
            $table->string('title');
            $table->json('payload')->nullable();
            $table->timestamp('visited_at')->useCurrent();

            $table->index('source_key');
            $table->index(['user_id', 'visited_at'], 'spotlight_recents_user_visited_idx');
            $table->unique(
                ['user_id', 'source_key', 'result_id'],
                'spotlight_recents_user_source_result_uq',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spotlight_recents');
    }
};

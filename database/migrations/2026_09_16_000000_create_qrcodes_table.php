<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qrcodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(0)->index();
            $table->string('type')->default('wechat_mini_app');
            $table->string('internal_name');
            $table->unique(['tenant_id', 'internal_name']);
            $table->string('scene')->nullable()->unique();
            $table->string('path')->nullable()->comment('WeChat Mini App page');
            $table->text('target_url')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('additional_data')->nullable();
            $table->json('urls')->nullable();
            $table->longText('default_data_url')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qrcodes');
    }
};

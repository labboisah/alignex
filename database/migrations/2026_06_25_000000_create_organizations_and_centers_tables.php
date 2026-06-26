<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('contact_person');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('centers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('location');
            $table->unsignedInteger('capacity')->default(0);
            $table->string('contact_person');
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('location');
            $table->unsignedInteger('capacity')->default(0);
            $table->string('contact_person');
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centers');
        Schema::dropIfExists('schools');
        Schema::dropIfExists('organizations');
    }
};

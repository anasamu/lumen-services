<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Example extends Migration
{
    public function up()
    {
        Schema::create('example', function (Blueprint $table) {
            $table->uuid('uuid')->primary()->unique();
            $table->string('name');
            $table->decimal('price');
            $table->integer('qty')->default(0);
            $table->longText('description')->nullable(true);
            $table->string('password');
            $table->string('foto')->nullable();
            $table->uuid('created_by')->nullable(true);
            $table->uuid('updated_by')->nullable(true);
            $table->uuid('uuid_sync')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('example');
    }
}

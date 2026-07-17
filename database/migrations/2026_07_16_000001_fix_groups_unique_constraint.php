<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            // Eliminar la constraint global de group_code que impide
            // que dos planes distintos (nuevo-modelo / plan-normal) tengan
            // el mismo código de grupo (ej. ILI10-4 en ambos planes).
            $table->dropUnique('uq_groups_code');

            // Nueva constraint: única por (career_id, group_code).
            // career_id ya distingue el plan, por lo que dos carreras
            // homónimas en planes diferentes pueden tener el mismo código.
            $table->unique(['career_id', 'group_code'], 'uq_groups_career_code');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique('uq_groups_career_code');
            $table->unique('group_code', 'uq_groups_code');
        });
    }
};

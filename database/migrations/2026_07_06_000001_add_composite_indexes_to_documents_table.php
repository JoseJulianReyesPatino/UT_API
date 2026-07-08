<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Listado de documentos de un docente ordenado por fecha (el caso más frecuente)
            $table->index(['uploaded_by', 'submitted_at'], 'idx_docs_user_date');

            // Filtrado por ciclo + estado (CiclosEscolares, DocumentReview)
            $table->index(['cycle_id', 'status'], 'idx_docs_cycle_status');

            // Filtrado por usuario + estado (pendientes/revisados de un docente)
            $table->index(['uploaded_by', 'status'], 'idx_docs_user_status');

            // Dashboard: documentos pendientes ordenados por fecha
            $table->index(['status', 'submitted_at'], 'idx_docs_status_date');

            // Documentos revisados hoy (reviewed_at ya existe, pero falta compuesto con status)
            $table->index(['status', 'reviewed_at'], 'idx_docs_status_reviewed');
        });

        // Índice compuesto en personal_access_tokens para búsqueda por usuario
        // (útil cuando se limpian tokens por usuario)
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->index(['tokenable_type', 'tokenable_id', 'created_at'], 'idx_pat_user_created');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_docs_user_date');
            $table->dropIndex('idx_docs_cycle_status');
            $table->dropIndex('idx_docs_user_status');
            $table->dropIndex('idx_docs_status_date');
            $table->dropIndex('idx_docs_status_reviewed');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_pat_user_created');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ampliar el ENUM en careers
        DB::statement("
            ALTER TABLE `careers`
            MODIFY COLUMN `plan`
            ENUM('nuevo_modelo', 'plan_normal', 'plan_despresurizada')
            COLLATE utf8mb4_unicode_ci
            NOT NULL
        ");

        // 2. Ampliar el ENUM en documents (también lo tenía limitado)
        DB::statement("
            ALTER TABLE `documents`
            MODIFY COLUMN `plan`
            ENUM('nuevo_modelo', 'plan_normal', 'plan_despresurizada')
            COLLATE utf8mb4_unicode_ci
            DEFAULT NULL
        ");
    }

    public function down(): void
    {
        // Revertir documents
        DB::statement("UPDATE `documents` SET `plan` = NULL WHERE `plan` = 'plan_despresurizada'");
        DB::statement("
            ALTER TABLE `documents`
            MODIFY COLUMN `plan`
            ENUM('nuevo_modelo', 'plan_normal')
            COLLATE utf8mb4_unicode_ci
            DEFAULT NULL
        ");

        // Revertir careers
        DB::statement("DELETE FROM `careers` WHERE `plan` = 'plan_despresurizada'");
        DB::statement("
            ALTER TABLE `careers`
            MODIFY COLUMN `plan`
            ENUM('nuevo_modelo', 'plan_normal')
            COLLATE utf8mb4_unicode_ci
            NOT NULL
        ");
    }
};

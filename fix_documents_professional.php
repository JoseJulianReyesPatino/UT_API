<?php
// fix_documents_professional.php
// Ejecutar: php fix_documents_professional.php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

echo "\n========================================\n";
echo "  ARREGLO PROFESIONAL DE DOCUMENTOS\n";
echo "========================================\n\n";

// 1. DIAGNOSTICAR UBICACIÓN REAL
echo "📁 DIAGNOSTICANDO UBICACIONES...\n";
echo "----------------------------------------\n";

$publicDocs = glob(public_path('uploads/**/*.pdf'), GLOB_NOSORT);
$storageDocs = glob(storage_path('app/public/uploads/**/*.pdf'), GLOB_NOSORT);

echo "Archivos en public/uploads/   : " . count($publicDocs) . "\n";
echo "Archivos en storage/app/public/: " . count($storageDocs) . "\n\n";

// 2. IDENTIFICAR DÓNDE DEBEN ESTAR
$shouldBeInPublic = count($publicDocs) > count($storageDocs);
$location = $shouldBeInPublic ? 'public/uploads/' : 'storage/app/public/';
echo "✅ Los archivos DEBEN estar en: $location\n\n";

// 3. VERIFICAR CONFIGURACIÓN ACTUAL DE STORAGE
$currentRoot = config('filesystems.disks.public.root');
echo "⚙️ Configuración actual de Storage::disk('public'):\n";
echo "   root: $currentRoot\n";
echo "   ¿Coincide? " . ($currentRoot == public_path('uploads') ? '✅ SÍ' : '❌ NO') . "\n\n";

// 4. CORREGIR CONFIGURACIÓN SI ES NECESARIO
if ($currentRoot != public_path('uploads') && $shouldBeInPublic) {
    echo "🔧 CORRIGIENDO CONFIGURACIÓN...\n";
    $config = file_get_contents(config_path('filesystems.php'));
    $config = preg_replace(
        "/'root' => [^,]+,/",
        "'root' => public_path('uploads'),",
        $config
    );
    file_put_contents(config_path('filesystems.php'), $config);
    echo "✅ Configuración actualizada\n\n";
}

// 5. CORREGIR BASE DE DATOS
echo "📊 CORRIGIENDO BASE DE DATOS...\n";
echo "----------------------------------------\n";

$count = DB::table('documents')
    ->where('file_path', 'LIKE', 'storage/uploads/%')
    ->count();

if ($count > 0) {
    echo "Documentos con 'storage/uploads/': $count\n";
    echo "Actualizando...\n";

    if ($shouldBeInPublic) {
        DB::table('documents')
            ->where('file_path', 'LIKE', 'storage/uploads/%')
            ->update([
                'file_path' => DB::raw("REPLACE(file_path, 'storage/uploads/', 'uploads/')")
            ]);
        echo "✅ Cambiado a 'uploads/'\n";
    }

    $newCount = DB::table('documents')
        ->where('file_path', 'LIKE', 'uploads/%')
        ->count();
    echo "Documentos con 'uploads/': $newCount\n";
} else {
    echo "✅ No hay documentos con 'storage/uploads/'\n";
}

// 6. VERIFICAR DOCUMENTO 36
echo "\n📄 VERIFICANDO DOCUMENTO ID 36:\n";
echo "----------------------------------------\n";

$doc36 = DB::table('documents')->where('id', 36)->first();
if ($doc36) {
    echo "Título: {$doc36->title}\n";
    echo "Ruta en BD: {$doc36->file_path}\n";

    // Probar con Storage
    $exists = Storage::disk('public')->exists($doc36->file_path);
    echo "Storage::disk('public')->exists(): " . ($exists ? '✅ SÍ' : '❌ NO') . "\n";

    // Probar con file_exists
    $fullPath = public_path($doc36->file_path);
    $exists2 = file_exists($fullPath);
    echo "file_exists(public_path()): " . ($exists2 ? '✅ SÍ' : '❌ NO') . "\n";
    echo "Ruta completa: $fullPath\n";

    if (!$exists2) {
        echo "\n🔍 Buscando el archivo por nombre:\n";
        $fileName = basename($doc36->file_path);
        $found = glob(public_path('uploads/**/*' . $fileName));
        if (!empty($found)) {
            echo "✅ Encontrado en: " . str_replace(public_path(), '', $found[0]) . "\n";
            echo "   Recomendación: Actualiza la ruta en BD a: " . str_replace(public_path('uploads/'), '', $found[0]) . "\n";
        } else {
            echo "❌ Archivo NO encontrado en ningún lugar\n";
            echo "   Posible causa: El archivo fue eliminado manualmente\n";
        }
    }
}

// 7. LIMPIAR CACHÉ
echo "\n🧹 LIMPIANDO CACHÉ...\n";
echo "----------------------------------------\n";
exec('php artisan config:clear');
exec('php artisan cache:clear');
echo "✅ Caché limpiado\n";

echo "\n========================================\n";
echo "  ✅ ARREGLO COMPLETADO\n";
echo "========================================\n\n";

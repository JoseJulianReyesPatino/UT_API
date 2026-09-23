<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$active = App\Models\AcademicCycle::where('status', 'activo')->first();
$d = App\Models\Document::orderByDesc('id')->first();

echo "Ciclo activo: " . $active->id . " | " . $active->name . PHP_EOL;
echo "Ultimo doc: ID=" . $d->id . " | cycle_id=" . $d->cycle_id . " | " . $d->title . PHP_EOL;

if ($d->cycle_id === $active->id) {
    echo "✅ CORRECTO: doc va al ciclo activo" . PHP_EOL;
} else {
    echo "❌ BUG: doc va al ciclo " . $d->cycle_id . " en vez del activo " . $active->id . PHP_EOL;
}

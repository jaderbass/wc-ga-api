<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionFixPlan extends Command
{
  protected $signature = 'permissions:fix-plan
        {--to=web : Ziel-Guard-Name (z.B. web, filament)}
        {--limit=50000 : Max. Datensätze pro Kategorie}
        {--csv= : Pfad für CSV (z.B. storage/app/perm_fix_plan.csv)}
        {--sql= : Pfad für SQL (z.B. storage/app/perm_fix_plan.sql)}';

  protected $description = 'Erzeugt einen rein dokumentierten Fix-Plan (CSV & optional SQL) für Guard-Korrekturen, Orphans und Duplikate – ohne Änderungen an der DB.';

  public function handle(): int
  {
    $target     = (string) ($this->option('to') ?? 'web');
    $limit      = (int) ($this->option('limit') ?? 50000);
    $csvPath    = $this->option('csv');
    $sqlPath    = $this->option('sql');

    $this->info("== Permission Fix Plan ==");
    $this->line("Ziel-Guard: {$target}");
    $this->newLine();

    $rows = [];
    $sql  = [];

    // 1) Rollen mit falschem Guard
    $this->section('Rollen mit Guard != target');
    $badRoles = Role::where('guard_name', '!=', $target)->limit($limit)->get(['id', 'name', 'guard_name']);
    foreach ($badRoles as $r) {
      $rows[] = ['ROLE_GUARD', "roles.id={$r->id}", $r->name, $r->guard_name, $target, "UPDATE roles SET guard_name='{$target}' WHERE id={$r->id};"];
      $sql[]  = "UPDATE roles SET guard_name='{$target}' WHERE id={$r->id};";
    }
    $this->line('Betroffene Rollen: ' . $badRoles->count());

    // 2) Permissions mit falschem Guard
    $this->section('Permissions mit Guard != target');
    $badPerms = Permission::where('guard_name', '!=', $target)->limit($limit)->get(['id', 'name', 'guard_name']);
    foreach ($badPerms as $p) {
      $rows[] = ['PERM_GUARD', "permissions.id={$p->id}", $p->name, $p->guard_name, $target, "UPDATE permissions SET guard_name='{$target}' WHERE id={$p->id};"];
      $sql[]  = "UPDATE permissions SET guard_name='{$target}' WHERE id={$p->id};";
    }
    $this->line('Betroffene Permissions: ' . $badPerms->count());

    // 3) Orphans in model_has_roles
    $this->section('Orphans: model_has_roles ohne passende roles');
    $orphMhr = DB::table('model_has_roles as m')
      ->leftJoin('roles as r', 'r.id', '=', 'm.role_id')
      ->whereNull('r.id')
      ->limit($limit)
      ->get(['m.model_type', 'm.model_id', 'm.role_id']);
    foreach ($orphMhr as $o) {
      $where = "model_type='{$o->model_type}' AND model_id={$o->model_id} AND role_id={$o->role_id}";
      $rows[] = ['ORPHAN_MHR', $where, '-', '-', '-', "DELETE FROM model_has_roles WHERE {$where} LIMIT 1;"];
      $sql[]  = "DELETE FROM model_has_roles WHERE {$where} LIMIT 1;";
    }
    $this->line('Orphans (mhr): ' . $orphMhr->count());

    // 4) Orphans in model_has_permissions
    $this->section('Orphans: model_has_permissions ohne passende permissions');
    $orphMhp = DB::table('model_has_permissions as m')
      ->leftJoin('permissions as p', 'p.id', '=', 'm.permission_id')
      ->whereNull('p.id')
      ->limit($limit)
      ->get(['m.model_type', 'm.model_id', 'm.permission_id']);
    foreach ($orphMhp as $o) {
      $where = "model_type='{$o->model_type}' AND model_id={$o->model_id} AND permission_id={$o->permission_id}";
      $rows[] = ['ORPHAN_MHP', $where, '-', '-', '-', "DELETE FROM model_has_permissions WHERE {$where} LIMIT 1;"];
      $sql[]  = "DELETE FROM model_has_permissions WHERE {$where} LIMIT 1;";
    }
    $this->line('Orphans (mhp): ' . $orphMhp->count());

    // 5) Duplikate in model_has_roles
    $this->section('Duplikate: model_has_roles');
    $dupMhr = DB::table('model_has_roles')
      ->select('model_type', 'model_id', 'role_id', DB::raw('COUNT(*) as c'))
      ->groupBy('model_type', 'model_id', 'role_id')
      ->havingRaw('COUNT(*) > 1')
      ->limit($limit)->get();
    foreach ($dupMhr as $d) {
      $where = "model_type='{$d->model_type}' AND model_id={$d->model_id} AND role_id={$d->role_id}";
      // Lösche n-1 (hier als Hinweis/Plan ohne konkrete LIMIT-Schleifen)
      $rows[] = ['DUP_MHR', $where, '-', '-', '-', "-- Duplikate reduzieren auf 1 Eintrag:"];
      $rows[] = ['DUP_MHR', $where, '-', '-', '-', "/* Beispiel */ DELETE FROM model_has_roles WHERE {$where} LIMIT " . ((int)$d->c - 1) . ";"];
      $sql[]  = "-- Duplikate model_has_roles";
      $sql[]  = "DELETE FROM model_has_roles WHERE {$where} LIMIT " . ((int)$d->c - 1) . ";";
    }
    $this->line('Duplikate (mhr): ' . $dupMhr->count());

    // 6) Duplikate in model_has_permissions
    $this->section('Duplikate: model_has_permissions');
    $dupMhp = DB::table('model_has_permissions')
      ->select('model_type', 'model_id', 'permission_id', DB::raw('COUNT(*) as c'))
      ->groupBy('model_type', 'model_id', 'permission_id')
      ->havingRaw('COUNT(*) > 1')
      ->limit($limit)->get();
    foreach ($dupMhp as $d) {
      $where = "model_type='{$d->model_type}' AND model_id={$d->model_id} AND permission_id={$d->permission_id}";
      $rows[] = ['DUP_MHP', $where, '-', '-', '-', "-- Duplikate reduzieren auf 1 Eintrag:"];
      $rows[] = ['DUP_MHP', $where, '-', '-', '-', "/* Beispiel */ DELETE FROM model_has_permissions WHERE {$where} LIMIT " . ((int)$d->c - 1) . ";"];
      $sql[]  = "-- Duplikate model_has_permissions";
      $sql[]  = "DELETE FROM model_has_permissions WHERE {$where} LIMIT " . ((int)$d->c - 1) . ";";
    }
    $this->line('Duplikate (mhp): ' . $dupMhp->count());

    // 7) Guard-Mismatches zwischen Model und Rolle/Permission (Report + Kommentar)
    $this->section('Report: Guard-Mismatches (Model vs Role/Permission)');
    $mismatchRows = $this->scanGuardMismatches($target, $limit);
    foreach ($mismatchRows as $r) {
      // r = [pivot, model_type, model_id, item, item_guard, model_guard, note]
      $rows[] = ['MISMATCH', "{$r[1]}#{$r[2]}", $r[3], $r[4], $r[5], "-- Prüfen: Entweder Rolle/Permission oder Modell-Guard vereinheitlichen ({$target})"];
    }
    $this->line('Mismatches gefunden: ' . count($mismatchRows));

    // CSV schreiben
    if ($csvPath) {
      $absCsv = $this->writeCsv($csvPath, ['TYPE', 'WHERE/REF', 'NAME', 'GUARD_OLD', 'GUARD_NEW/TARGET', 'ACTION_SQL_OR_NOTE'], $rows);
      $this->info("CSV geschrieben: {$absCsv}");
    } else {
      $this->warn('Kein --csv Pfad angegeben – CSV wird nicht erzeugt.');
    }

    // SQL schreiben
    if ($sqlPath) {
      $absSql = $this->writeSql($sqlPath, $sql, $target);
      $this->info("SQL geschrieben: {$absSql}");
      $this->warn('Hinweis: SQL‑Plan enthält LIMIT‑Löschungen für Duplikate. Prüfe vorher per SELECT.');
    } else {
      $this->warn('Kein --sql Pfad angegeben – SQL wird nicht erzeugt.');
    }

    $this->newLine();
    $this->info('Fix‑Plan erstellt (keine DB‑Änderungen vorgenommen).');
    $this->line('Empfehlung: Vor realen Änderungen Backup erzeugen und zuerst DRY prüfen.');
    return self::SUCCESS;
  }

  private function scanGuardMismatches(string $fallbackGuard, int $limit): array
  {
    $rows = [];

    // model_has_roles
    $pivots = DB::table('model_has_roles as m')
      ->join('roles as r', 'r.id', '=', 'm.role_id')
      ->select('m.model_type', 'm.model_id', 'r.name as item', 'r.guard_name as g')
      ->limit($limit)->get();
    foreach ($pivots as $p) {
      [$mg, $note] = $this->resolveModelGuard($p->model_type, $p->model_id, $fallbackGuard);
      if ($mg && $p->g !== $mg) {
        $rows[] = ['model_has_roles', $p->model_type, $p->model_id, $p->item, $p->g, $mg, $note ?: 'Mismatch'];
      }
    }

    // model_has_permissions
    $pivots = DB::table('model_has_permissions as m')
      ->join('permissions as p', 'p.id', '=', 'm.permission_id')
      ->select('m.model_type', 'm.model_id', 'p.name as item', 'p.guard_name as g')
      ->limit($limit)->get();
    foreach ($pivots as $p) {
      [$mg, $note] = $this->resolveModelGuard($p->model_type, $p->model_id, $fallbackGuard);
      if ($mg && $p->g !== $mg) {
        $rows[] = ['model_has_permissions', $p->model_type, $p->model_id, $p->item, $p->g, $mg, $note ?: 'Mismatch'];
      }
    }

    return $rows;
  }

  private function resolveModelGuard(string $modelType, int|string $modelId, string $fallbackGuard): array
  {
    if (!class_exists($modelType)) {
      return [$fallbackGuard, 'Model-Klasse fehlt – fallback'];
    }
    /** @var Model $model */
    $model = $modelType::query()->find($modelId);
    if (!$model) {
      return [$fallbackGuard, 'Model-Instanz fehlt – fallback'];
    }

    $guard = property_exists($model, 'guard_name') ? $model->guard_name : null;
    if (!$guard && method_exists($model, 'getDefaultGuardName')) {
      $guard = $model->getDefaultGuardName();
    }
    return [$guard ?: $fallbackGuard, $guard ? '' : 'Fallback auf default guard'];
  }

  private function writeCsv(string $path, array $headers, array $rows): string
  {
    if (!str_contains($path, DIRECTORY_SEPARATOR)) {
      $path = 'app/' . $path;
    }
    $abs = storage_path($path);
    @mkdir(dirname($abs), 0777, true);
    $fh = fopen($abs, 'w');
    fputcsv($fh, $headers);
    foreach ($rows as $r) fputcsv($fh, $r);
    fclose($fh);
    return $abs;
  }

  private function writeSql(string $path, array $sqlLines, string $target): string
  {
    if (!str_contains($path, DIRECTORY_SEPARATOR)) {
      $path = 'app/' . $path;
    }
    $abs = storage_path($path);
    @mkdir(dirname($abs), 0777, true);
    $banner = [
      "-- Permission Fix Plan (nur Vorschläge) --",
      "-- Ziel-Guard: {$target}",
      "-- Bitte vor Ausführung prüfen & Backup erstellen.",
      "START TRANSACTION;",
    ];
    $footer = [
      "COMMIT;",
      "-- Ende Fix-Plan --",
    ];
    file_put_contents($abs, implode(PHP_EOL, array_merge($banner, $sqlLines, $footer)) . PHP_EOL);
    return $abs;
  }

  private function section(string $title): void
  {
    $this->newLine();
    $this->info("== {$title} ==");
  }
}

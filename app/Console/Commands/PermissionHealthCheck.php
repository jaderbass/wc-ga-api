<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionHealthCheck extends Command
{
  protected $signature = 'permissions:health
        {--fix-orphans : Löscht nur eindeutig verwaiste Pivot-Einträge (sicher)}
        {--limit=5000 : Max. Anzahl Pivots pro Prüfpass}
        {--export-mismatches= : Pfad/Dateiname für CSV-Export der Guard-Mismatches (z.B. storage/app/perm_mismatches.csv)}
        {--export-stats= : Pfad/Dateiname für CSV-Export der Guard-Verteilung (z.B. storage/app/perm_stats.csv)}';

  protected $description = 'Prüft Integrität von Rollen/Permissions & Pivots; optional CSV-Exporte für Mismatches und Statistiken.';

  public function handle(): int
  {
    $limit      = (int) ($this->option('limit') ?? 5000);
    $doFix      = (bool) $this->option('fix-orphans');
    $exportMM   = $this->option('export-mismatches'); // string|null
    $exportStats = $this->option('export-stats');      // string|null
    $defaultGuard = Config::get('auth.defaults.guard', 'web');

    $this->info('== Permission Health Check ==');
    $this->line("Default guard: {$defaultGuard}");
    $this->line('Modus: ' . ($doFix ? 'Fix Orphans' : 'Report only'));
    $this->newLine();

    // 1) Verteilung anzeigen (+ optional exportieren)
    $this->section('Verteilung Rollen & Permissions nach guard_name');
    $statsRows = $this->distributionRows();
    $this->table(['Typ', 'Guard', 'Anzahl'], $statsRows);

    if ($exportStats) {
      $written = $this->writeCsv($exportStats, ['type', 'guard_name', 'count'], $statsRows);
      $this->info("Stats exportiert: {$written}");
    }

    // 2) Orphans in Pivots
    $this->section('Orphans in Pivots');
    $orphansRoles = DB::table('model_has_roles as m')
      ->leftJoin('roles as r', 'r.id', '=', 'm.role_id')
      ->whereNull('r.id')
      ->limit($limit)->get(['m.role_id', 'm.model_type', 'm.model_id']);

    $orphansPerms = DB::table('model_has_permissions as m')
      ->leftJoin('permissions as p', 'p.id', '=', 'm.permission_id')
      ->whereNull('p.id')
      ->limit($limit)->get(['m.permission_id', 'm.model_type', 'm.model_id']);

    $this->line('model_has_roles ohne passende roles: ' . $orphansRoles->count());
    $this->line('model_has_permissions ohne passende permissions: ' . $orphansPerms->count());

    if ($doFix) {
      $this->fixOrphanRoles($limit);
      $this->fixOrphanPermissions($limit);
    }

    // 3) Orphans (fehlende Modelle)
    $this->section('Orphans: referenzierte Modelle fehlen');
    [$mhrMissingModels, $mhpMissingModels] = $this->findMissingModels($limit);
    $this->line('model_has_roles mit fehlendem Model: ' . $mhrMissingModels);
    $this->line('model_has_permissions mit fehlendem Model: ' . $mhpMissingModels);
    if ($doFix && ($mhrMissingModels || $mhpMissingModels)) {
      $this->warn('Hinweis: Automatisches Löschen fehlender Modelle wird NICHT durchgeführt. Manuell bewerten.');
    }

    // 4) Guard‑Mismatches (Rolle/Permission vs. Model‑Guard) + optionaler CSV‑Export
    $this->section('Guard‑Konsistenz prüfen');
    $guardReport = $this->scanGuardMismatches($limit, $defaultGuard);
    $this->table(['Pivot', 'Model Type', 'Model ID', 'Role/Perm', 'Guard(role/perm)', 'Guard(model)', 'Hinweis'], $guardReport['rows']);
    $this->line('Summe Guard‑Mismatches: ' . $guardReport['count']);

    if ($exportMM) {
      $written = $this->writeCsv(
        $exportMM,
        ['pivot', 'model_type', 'model_id', 'item', 'item_guard', 'model_guard', 'note'],
        $guardReport['rows']
      );
      $this->info("Mismatches exportiert: {$written}");
    }

    // 5) Duplikate
    $this->section('Duplikate in Pivots');
    $dupRoles = DB::table('model_has_roles')
      ->select('model_type', 'model_id', 'role_id', DB::raw('COUNT(*) as c'))
      ->groupBy('model_type', 'model_id', 'role_id')->havingRaw('COUNT(*) > 1')->limit($limit)->get();
    $dupPerms = DB::table('model_has_permissions')
      ->select('model_type', 'model_id', 'permission_id', DB::raw('COUNT(*) as c'))
      ->groupBy('model_type', 'model_id', 'permission_id')->havingRaw('COUNT(*) > 1')->limit($limit)->get();

    $this->line('Duplikate model_has_roles: ' . $dupRoles->count());
    $this->line('Duplikate model_has_permissions: ' . $dupPerms->count());
    if ($doFix) {
      $this->fixDuplicates('model_has_roles', ['model_type', 'model_id', 'role_id']);
      $this->fixDuplicates('model_has_permissions', ['model_type', 'model_id', 'permission_id']);
    }

    $this->newLine();
    $this->info(
      'Health‑Check abgeschlossen.'
        . ($doFix ? ' (Orphans/Duplikate bereinigt, wo sicher möglich)' : '')
        . ($exportMM || $exportStats ? ' (CSV exportiert)' : '')
    );

    return self::SUCCESS;
  }

  private function distributionRows(): array
  {
    $rows = [];
    $roleDist = Role::select('guard_name', DB::raw('COUNT(*) as cnt'))->groupBy('guard_name')->get();
    foreach ($roleDist as $r) {
      $rows[] = ['Role', $r->guard_name, $r->cnt];
    }
    $permDist = Permission::select('guard_name', DB::raw('COUNT(*) as cnt'))->groupBy('guard_name')->get();
    foreach ($permDist as $p) {
      $rows[] = ['Permission', $p->guard_name, $p->cnt];
    }
    return $rows;
  }

  private function fixOrphanRoles(int $limit): void
  {
    $deleted = DB::table('model_has_roles')
      ->whereIn('role_id', function ($q) {
        $q->select('m.role_id')
          ->from('model_has_roles as m')
          ->leftJoin('roles as r', 'r.id', '=', 'm.role_id')
          ->whereNull('r.id');
      })->limit($limit)->delete();
    $this->info("Bereinigt: model_has_roles Orphans (fehlende roles): {$deleted}");
  }

  private function fixOrphanPermissions(int $limit): void
  {
    $deleted = DB::table('model_has_permissions')
      ->whereIn('permission_id', function ($q) {
        $q->select('m.permission_id')
          ->from('model_has_permissions as m')
          ->leftJoin('permissions as p', 'p.id', '=', 'm.permission_id')
          ->whereNull('p.id');
      })->limit($limit)->delete();
    $this->info("Bereinigt: model_has_permissions Orphans (fehlende permissions): {$deleted}");
  }

  private function findMissingModels(int $limit): array
  {
    $countMhr = 0;
    $countMhp = 0;

    $pivots = DB::table('model_has_roles')->select('model_type', 'model_id')->limit($limit)->get();
    foreach ($pivots as $p) {
      if (! class_exists($p->model_type)) {
        $countMhr++;
        continue;
      }
      /** @var Model $class */
      $class = $p->model_type;
      if (! $class::query()->whereKey($p->model_id)->exists()) $countMhr++;
    }

    $pivots = DB::table('model_has_permissions')->select('model_type', 'model_id')->limit($limit)->get();
    foreach ($pivots as $p) {
      if (! class_exists($p->model_type)) {
        $countMhp++;
        continue;
      }
      /** @var Model $class */
      $class = $p->model_type;
      if (! $class::query()->whereKey($p->model_id)->exists()) $countMhp++;
    }

    return [$countMhr, $countMhp];
  }

  private function scanGuardMismatches(int $limit, string $fallbackGuard): array
  {
    $rows = [];
    $count = 0;

    // Roles
    $pivots = DB::table('model_has_roles as m')
      ->join('roles as r', 'r.id', '=', 'm.role_id')
      ->select('m.model_type', 'm.model_id', 'r.name as role', 'r.guard_name as r_guard')
      ->limit($limit)->get();

    foreach ($pivots as $p) {
      [$modelGuard, $note] = $this->resolveModelGuard($p->model_type, $p->model_id, $fallbackGuard);
      if ($modelGuard && $p->r_guard !== $modelGuard) {
        $count++;
        $rows[] = ['model_has_roles', $p->model_type, $p->model_id, $p->role, $p->r_guard, $modelGuard, $note ?: 'Mismatch'];
      }
    }

    // Permissions
    $pivots = DB::table('model_has_permissions as m')
      ->join('permissions as p', 'p.id', '=', 'm.permission_id')
      ->select('m.model_type', 'm.model_id', 'p.name as perm', 'p.guard_name as p_guard')
      ->limit($limit)->get();

    foreach ($pivots as $p) {
      [$modelGuard, $note] = $this->resolveModelGuard($p->model_type, $p->model_id, $fallbackGuard);
      if ($modelGuard && $p->p_guard !== $modelGuard) {
        $count++;
        $rows[] = ['model_has_permissions', $p->model_type, $p->model_id, $p->perm, $p->p_guard, $modelGuard, $note ?: 'Mismatch'];
      }
    }

    return ['rows' => $rows, 'count' => $count];
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

    // $guard_name Property auf dem Model?
    $guard = property_exists($model, 'guard_name') ? $model->guard_name : null;

    if (! $guard && method_exists($model, 'getDefaultGuardName')) {
      $guard = $model->getDefaultGuardName();
    }

    return [$guard ?: $fallbackGuard, $guard ? '' : 'Fallback auf default guard'];
  }

  private function fixDuplicates(string $table, array $cols): void
  {
    // Entfernt Duplikate, behält je Gruppe einen Eintrag
    $groups = DB::table($table)
      ->select($cols)
      ->selectRaw('COUNT(*) as c')
      ->groupBy($cols)
      ->havingRaw('COUNT(*) > 1')
      ->get();

    $deletedTotal = 0;
    foreach ($groups as $g) {
      $rows = DB::table($table)->where(function ($q) use ($cols, $g) {
        foreach ($cols as $col) $q->where($col, '=', $g->$col);
      })->get();

      $keep = true;
      foreach ($rows as $row) {
        if ($keep) {
          $keep = false;
          continue;
        }
        DB::table($table)->where(function ($q) use ($cols, $row) {
          foreach ($cols as $col) $q->where($col, '=', $row->$col);
        })->limit(1)->delete();
        $deletedTotal++;
      }
    }
    $this->info("Bereinigt: {$table} Duplikate entfernt: {$deletedTotal}");
  }

  private function writeCsv(string $path, array $headers, array $rows): string
  {
    // Pfad normalisieren – falls nur Dateiname gegeben, schreibe in storage/app
    if (!str_contains($path, DIRECTORY_SEPARATOR)) {
      $path = 'app/' . $path;
    }
    $absolute = storage_path($path);

    // Sicherstellen, dass Verzeichnis existiert
    @mkdir(dirname($absolute), 0777, true);

    $fh = fopen($absolute, 'w');
    fputcsv($fh, $headers);
    foreach ($rows as $r) fputcsv($fh, $r);
    fclose($fh);

    return $absolute;
  }

  private function section(string $title): void
  {
    $this->newLine();
    $this->info("== {$title} ==");
  }
}

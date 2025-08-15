<?php

/**
 * ein kleines, robustes Artisan‑Kommando, das Rollen und Permissions auf einen gewünschten Guard 
 * (Standard: web) prüft und bei Bedarf korrigiert. 
 * Es zeigt dir vorher eine Zusammenfassung und kann als Dry‑Run laufen
 * 
 * path app/Console/Commands/FixPermissionGuards.php
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class FixPermissionGuards extends Command
{
  protected $signature = 'permissions:fix-guards 
                            {--to=web : Ziel-Guard-Name (z.B. web, filament)} 
                            {--apply : Änderungen wirklich ausführen (ohne --apply nur Dry-Run)}';

  protected $description = 'Prüft und korrigiert guard_name von Rollen & Permissions.';

  public function handle(): int
  {
    $target = $this->option('to') ?? 'web';
    $apply  = (bool) $this->option('apply');

    $this->info("Ziel-Guard: {$target}");
    $this->line($apply ? 'Modus: APPLY (Änderungen werden geschrieben)' : 'Modus: DRY-RUN (keine Schreiboperationen)');

    // Aktuelle Verteilung anzeigen
    $this->section('Aktuelle Verteilung');
    $this->table(
      ['Typ', 'guard_name', 'Anzahl'],
      $this->distributionRows()
    );

    // Kandidaten ermitteln
    $badRoles = Role::where('guard_name', '!=', $target)->get();
    $badPerms = Permission::where('guard_name', '!=', $target)->get();

    $this->section('Abweichungen');
    $this->line("Rollen ≠ '{$target}': " . $badRoles->count());
    $this->line("Permissions ≠ '{$target}': " . $badPerms->count());

    if ($badRoles->isEmpty() && $badPerms->isEmpty()) {
      $this->info('Alles sauber. Keine Abweichungen gefunden.');
      return self::SUCCESS;
    }

    if (!$apply) {
      $this->warn('DRY-RUN beendet. Starte mit --apply, um zu korrigieren.');
      return self::SUCCESS;
    }

    // Sicher ändern (Transaktion)
    DB::transaction(function () use ($badRoles, $badPerms, $target) {
      $badRoles->each(function (Role $role) use ($target) {
        $old = $role->guard_name;
        $role->guard_name = $target;
        $role->save();
        $this->line("Role #{$role->id} '{$role->name}': {$old} → {$target}");
      });

      $badPerms->each(function (Permission $perm) use ($target) {
        $old = $perm->guard_name;
        $perm->guard_name = $target;
        $perm->save();
        $this->line("Perm #{$perm->id} '{$perm->name}': {$old} → {$target}");
      });
    });

    // Cache resetten, damit Spatie die Änderungen sieht
    $this->callSilent('permission:cache-reset');

    $this->section('Nach der Korrektur');
    $this->table(
      ['Typ', 'guard_name', 'Anzahl'],
      $this->distributionRows()
    );

    $this->info('Fertig.');
    return self::SUCCESS;
  }

  private function distributionRows(): array
  {
    $rows = [];

    $roleDist = Role::select('guard_name', DB::raw('COUNT(*) as cnt'))
      ->groupBy('guard_name')->get();
    foreach ($roleDist as $r) {
      $rows[] = ['Role', $r->guard_name, $r->cnt];
    }

    $permDist = Permission::select('guard_name', DB::raw('COUNT(*) as cnt'))
      ->groupBy('guard_name')->get();
    foreach ($permDist as $p) {
      $rows[] = ['Permission', $p->guard_name, $p->cnt];
    }
    return $rows;
  }

  private function section(string $title): void
  {
    $this->line('');
    $this->info("== {$title} ==");
  }
}

<?php

namespace App\Support\Woo;

/**
 * Synchronisationsstatus für den Woo-Outbound.
 *
 * @method static self Synced()
 * @method static self Failed()
 */
enum SyncStatus: string {
    case Pending = 'pending';
    case Synced  = 'synced';
    case Error   = 'error';
    case Deleted = 'deleted';
    case Failed = 'failed';
}

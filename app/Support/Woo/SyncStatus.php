<?php

namespace App\Support\Woo;

enum SyncStatus: string {
    case Pending = 'pending';
    case Synced  = 'synced';
    case Error   = 'error';
    case Deleted = 'deleted';
}

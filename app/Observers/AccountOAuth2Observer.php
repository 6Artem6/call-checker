<?php

namespace App\Observers;

use App\Models\AccountOAuth2;
use Illuminate\Support\Facades\Log;

class AccountOAuth2Observer
{
    /**
     * Handle the AccountOAuth2 "created" event.
     */
    public function created(AccountOAuth2 $model): void
    {
        Log::info('Создана запись', ['id' => $model->id, 'data' => $model->toArray()]);
    }

    /**
     * Handle the AccountOAuth2 "updated" event.
     */
    public function updated(AccountOAuth2 $model): void
    {
        Log::info('Обновлена запись', ['id' => $model->id, 'data' => $model->getChanges()]);
    }

    /**
     * Handle the AccountOAuth2 "deleted" event.
     */
    public function deleted(AccountOAuth2 $model): void
    {
        Log::info('Удалена запись', ['id' => $model->id, 'data' => $model->toArray()]);
    }

    /**
     * Handle the AccountOAuth2 "restored" event.
     */
    public function restored(AccountOAuth2 $model): void
    {
        //
    }

    /**
     * Handle the AccountOAuth2 "force deleted" event.
     */
    public function forceDeleted(AccountOAuth2 $model): void
    {
        //
    }
}

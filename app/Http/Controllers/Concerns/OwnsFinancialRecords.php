<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait OwnsFinancialRecords
{
    protected function owned(Request $request, Model $model): Model
    {
        abort_unless((int) $model->getAttribute('user_id') === (int) $request->user()->id, 404);

        return $model;
    }
}

<?php
namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait GeneratesCode
{
    /**
     * Register a creating model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    abstract static public function creating($callback);

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    abstract public function getTable();

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    abstract public function getKeyName();

    /**
     * Get the code prefix for the model.
     *
     * @return string
     */
    abstract public function getCodePrefix();

    public static function bootGeneratesCode()
    {
        static::creating(function ($model) {
            $keyName = $model->getKeyName();
            if (empty($model->{$keyName})) {
                $prefix = $model->getCodePrefix();
                
                $lastRecord = DB::table($model->getTable())
                    ->where($keyName, 'LIKE', $prefix . '%')
                    ->orderBy($keyName, 'desc')
                    ->first();
                
                if (!$lastRecord) {
                    $number = 1;
                } else {
                    $lastCode = $lastRecord->{$keyName};
                    // Lấy các chữ số cuối cùng
                    $numberStr = substr($lastCode, strlen($prefix));
                    $number = intval($numberStr) + 1;
                }

                $model->{$keyName} = $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}

<?php

namespace App\Traits;

use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;

/**
 * مزامنة تلقائية لأي تغيير في MySQL إلى مرآة Firestore
 * (تعمل فقط عند تفعيل Firebase — وإلا تُتجاهل بصمت).
 */
trait SyncsToFirestore
{
    public static function bootSyncsToFirestore()
    {
        static::created(fn($m) => self::pushToFirestore($m));
        static::updated(fn($m) => self::pushToFirestore($m));
        static::deleted(fn($m) => self::removeFromFirestore($m));
    }

    protected static function pushToFirestore($model)
    {
        try {
            if (FirebaseService::enabled()) {
                (new FirebaseService)->syncModel($model->fresh() ?? $model);
            }
        } catch (\Exception $e) {
            Log::warning('Firestore sync skipped: ' . $e->getMessage());
        }
    }

    protected static function removeFromFirestore($model)
    {
        try {
            if (FirebaseService::enabled()) {
                // أسماء المجموعات مطابقة لأسماء الجداول (كما في syncModel)
                (new FirebaseService)->firestoreDelete($model->getTable(), $model->getKey());
            }
        } catch (\Exception $e) {
            Log::warning('Firestore delete skipped: ' . $e->getMessage());
        }
    }
}

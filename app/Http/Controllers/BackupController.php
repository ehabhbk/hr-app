<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    public function index()
    {
        return response()->json(Backup::orderByDesc('created_at')->get());
    }

    public function create()
    {
        $filename = 'backup_' . now()->format('Y-m-d_His') . '.sql';
        $path = storage_path('app/backups');
        if (!File::isDirectory($path)) File::makeDirectory($path, 0755, true);

        $fullPath = $path . '/' . $filename;

        try {
            $db = config('database.connections.mysql.database');
            $user = config('database.connections.mysql.username');
            $pass = config('database.connections.mysql.password');
            $host = config('database.connections.mysql.host');

            $cmd = "mysqldump -h {$host} -u {$user} --password=\"{$pass}\" {$db} > \"{$fullPath}\" 2>&1";
            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception(implode("\n", $output));
            }

            $backup = Backup::create([
                'filename' => $filename,
                'disk' => 'local',
                'size' => File::size($fullPath),
                'status' => 'success',
            ]);

            return response()->json(['message' => 'تم إنشاء النسخة الاحتياطية', 'data' => $backup], 201);
        } catch (\Exception $e) {
            $backup = Backup::create([
                'filename' => $filename,
                'disk' => 'local',
                'size' => 0,
                'status' => 'failed',
                'notes' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'فشل إنشاء النسخة الاحتياطية', 'error' => $e->getMessage()], 500);
        }
    }

    public function download($id)
    {
        $backup = Backup::findOrFail($id);
        $path = storage_path('app/backups/' . $backup->filename);
        if (!file_exists($path)) {
            return response()->json(['message' => 'الملف غير موجود'], 404);
        }
        return response()->download($path, $backup->filename);
    }

    public function destroy($id)
    {
        $backup = Backup::findOrFail($id);
        $path = storage_path('app/backups/' . $backup->filename);
        if (file_exists($path)) File::delete($path);
        $backup->delete();
        return response()->json(['message' => 'تم حذف النسخة الاحتياطية']);
    }
}

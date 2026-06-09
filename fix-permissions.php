<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
if (!$user) {
    echo "لا يوجد مستخدمين\n";
    exit;
}

if (!$user->role) {
    echo "المستخدم ليس له دور، جاري إنشاء دور admin...\n";
    
    $role = App\Models\Role::firstOrCreate(
        ['name' => 'admin'],
        ['display_name' => 'مدير النظام', 'description' => 'صلاحيات كاملة', 'color' => '#dc2626']
    );
    $user->role_id = $role->id;
    $user->save();
    echo "تم إنشاء وتخصيص دور admin للمستخدم\n";
}

$user->refresh();
$user->load('role.permissions');

$perms = App\Models\Permission::pluck('id')->toArray();
if (empty($perms)) {
    echo "لا توجد صلاحيات، قم بإنشائها أولاً من صفحة الصلاحيات\n";
    exit;
}

$role = $user->role;
if ($role) {
    $role->permissions()->sync($perms);
    echo "تم إعطاء المستخدم '{$user->username}' جميع الصلاحيات (" . count($perms) . " صلاحية)\n";
} else {
    echo "لا يمكن العثور على دور المستخدم\n";
}

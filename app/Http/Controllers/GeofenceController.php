<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use Illuminate\Http\Request;

class GeofenceController extends Controller
{
    public function index()
    {
        return response()->json(Geofence::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|integer|min:10|max:10000',
        ]);
        $fence = Geofence::create($data);
        return response()->json(['message' => 'تم إنشاء المنطقة الجغرافية', 'data' => $fence], 201);
    }

    public function update(Request $request, $id)
    {
        $fence = Geofence::findOrFail($id);
        $fence->update($request->only(['name', 'latitude', 'longitude', 'radius', 'is_active']));
        return response()->json(['message' => 'تم تحديث المنطقة الجغرافية', 'data' => $fence]);
    }

    public function destroy($id)
    {
        Geofence::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف المنطقة الجغرافية']);
    }

    public function checkLocation(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);
        $fences = Geofence::where('is_active', true)->get();
        foreach ($fences as $fence) {
            $distance = $this->haversine($request->latitude, $request->longitude, $fence->latitude, $fence->longitude);
            if ($distance <= $fence->radius) {
                return response()->json(['inside' => true, 'geofence' => $fence->name]);
            }
        }
        return response()->json(['inside' => false]);
    }

    private function haversine($lat1, $lon1, $lat2, $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

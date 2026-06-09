<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray($request)
    {
        // Get permissions from the model's permissions attribute (JSON cast)
        $perms = $this->getAttributeValue('permissions') ?: [];
        
        // Normalize permissions
        $permsOut = [];
        if (is_string($perms)) {
            $decoded = json_decode($perms, true);
            $perms = is_array($decoded) ? $decoded : ($decoded ? [$decoded] : []);
        }
        if (is_array($perms)) {
            $permsOut = array_values($perms);
        }

        if (in_array('*', $permsOut, true)) {
            $permsOut = ['*'];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->name_ar ?? $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'permissions' => $permsOut,
        ];
    }
}
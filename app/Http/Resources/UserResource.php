<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $avatar = null;
        if ($this->avatar) {
            if (str_starts_with($this->avatar, 'http')) {
                $avatar = $this->avatar;
            } else {
                $raw = ltrim($this->avatar, '/');
                if (str_starts_with($raw, 'storage/')) {
                    $raw = substr($raw, strlen('storage/'));
                }
                $avatar = asset('storage/' . $raw);
            }
        }

        return [
            'id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name,
            'name' => $this->full_name,
            'email' => $this->email,
            'avatar' => $avatar,
            'role_id' => $this->role_id,
            'department_id' => $this->department_id,
            'is_active' => $this->is_active,
            'role' => $this->whenLoaded('role'),
            'department' => $this->whenLoaded('department'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}


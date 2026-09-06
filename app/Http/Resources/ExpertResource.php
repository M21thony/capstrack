<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'expert_id' => $this->expert_id,
            'name' => $this->name,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'suffix' => $this->suffix,
            'credentials' => $this->credentials,
            'program' => $this->program,
            'profile_picture' => $this->profile_picture,
            'max_teams' => $this->max_teams,
            'avatar_initials' => $this->avatarInitials(),
            'specialization' => $this->specialization,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'expertise' => ExpertiseResource::collection($this->whenLoaded('expertise')),
            'slots_used' => $this->slots_used ?? $this->teamAssignments->count(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'team_id' => $this->team_id,
            'team_name' => $this->team_name,
            'project_title' => $this->project_title,
            'team_status' => $this->team_status,
            'is_confirmed' => $this->is_confirmed,
            'member_count' => $this->member_count,
            'leader' => new StudentResource($this->whenLoaded('leader')),
            'members' => StudentResource::collection($this->whenLoaded('members')),
            'created_at' => $this->created_at,
        ];
    }
}

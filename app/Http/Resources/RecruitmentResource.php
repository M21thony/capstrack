<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecruitmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'recruitment_id' => $this->recruitment_id,
            'recruitment_goal' => $this->recruitment_goal,
            'roles' => $this->roles,
            'role_tags' => $this->tagLabels(),
            'looking_for' => $this->looking_for,
            'introduction' => $this->introduction,
            'member_count' => $this->member_count,
            'student' => new StudentResource($this->whenLoaded('student')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

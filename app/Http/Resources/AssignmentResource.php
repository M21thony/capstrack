<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'assignment_id' => $this->assignment_id,
            'team_id' => $this->team_id,
            'expert_id' => $this->expert_id,
            'status' => $this->status,
            'message' => $this->message,
            'proposal_file' => $this->proposal_file ? Storage::url($this->proposal_file) : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'expert' => new ExpertResource($this->whenLoaded('expert')),
        ];
    }
}

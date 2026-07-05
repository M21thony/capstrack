<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'student_id' => $this->student_id,
            'student_number' => $this->student_number,
            'email' => $this->email,
            'f_name' => $this->f_name,
            'm_name' => $this->m_name,
            'l_name' => $this->l_name,
            'p_picture' => $this->p_picture,
            'course' => $this->course,
            'year_section' => $this->year_section,
            'avatar_url' => $this->avatarUrl(),
            'avatar_initials' => $this->avatarInitials(),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\ExamApplication;
use App\Models\ExamSet;
use App\Models\User;
use RuntimeException;

/**
 * Exam packs and the content bank belong to the owner account.
 * Guests read that content only after the owner approves their application.
 */
class GuestExamAccess
{
    /** The account whose synced packs / content bank guests read from. */
    public function owner(): User
    {
        $owner = User::query()->where('role', '!=', 'guest')->orderBy('id')->first();
        if (! $owner) {
            throw new RuntimeException('Exam content is not available yet.');
        }

        return $owner;
    }

    public function application(User $guest, ExamSet $exam): ?ExamApplication
    {
        return ExamApplication::query()
            ->where('user_id', $guest->id)
            ->where('exam_set_id', $exam->id)
            ->first();
    }

    public function isApproved(User $guest, ExamSet $exam): bool
    {
        return $this->application($guest, $exam)?->status === ExamApplication::APPROVED;
    }
}

<?php

namespace App\Notifications;

use App\Models\AdvisoryNote;
use Illuminate\Notifications\Notification;

class AdviserSubmissionAssigned extends Notification
{
    public function __construct(public readonly AdvisoryNote $submission) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'               => 'adviser_submission_assigned',
            'title'              => 'Adviser submission assigned',
            'advisory_note_id'   => $this->submission->id,
            'submitting_party'   => $this->submission->submitting_party,
            'document_name'      => $this->submission->document_name,
            'message'            => "You have been assigned to review \"{$this->submission->document_name}\" from {$this->submission->submitting_party}.",
        ];
    }
}

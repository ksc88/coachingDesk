<?php

namespace App\Domain\Messaging;

use App\Models\Guardian;
use App\Models\NotificationOutbox;
use App\Models\NotificationTemplate;
use App\Models\Student;
use App\Support\Tenancy\TenantContext;

class NotificationOutboxService
{
    public function enqueue(
        string $channel,
        string $eventType,
        string $body,
        ?Guardian $guardian = null,
        ?Student $student = null,
        ?string $templateKey = null,
        array $payload = [],
        ?\DateTimeInterface $scheduledAt = null,
    ): NotificationOutbox {
        return NotificationOutbox::query()->create([
            'tenant_id' => TenantContext::id() ?? $student?->tenant_id ?? $guardian?->tenant_id,
            'channel' => $channel,
            'event_type' => $eventType,
            'recipient_phone' => $guardian?->phone,
            'recipient_email' => $guardian?->email,
            'recipient_name' => $guardian?->name,
            'student_id' => $student?->id,
            'guardian_id' => $guardian?->id,
            'template_key' => $templateKey,
            'body' => $body,
            'payload' => $payload,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt ?? now(),
        ]);
    }

    public function enqueueForStudentGuardians(
        Student $student,
        string $eventType,
        string $templateKey,
        array $vars,
        bool $absentOnlyDefault = true,
    ): void {
        $student->loadMissing('guardians');

        foreach ($student->guardians as $guardian) {
            $choice = $this->chooseChannel($guardian);

            if (! $choice) {
                continue;
            }

            [$channel] = $choice;

            if ($this->alreadyQueued($student, $guardian, $eventType, $vars, $channel)) {
                continue;
            }

            $template = $this->resolveTemplate($templateKey, $channel, $student->tenant_id);
            $body = $this->render($template?->body ?? $this->fallbackBody($templateKey), $vars);

            $this->enqueue($channel, $eventType, $body, $guardian, $student, $templateKey, $vars);
        }
    }

    /**
     * WhatsApp first, then SMS, then email.
     * One channel only — never WhatsApp + SMS + email together.
     *
     * @return array{0: string}|null
     */
    public function chooseChannel(Guardian $guardian): ?array
    {
        if ($guardian->whatsapp_opt_in && filled($guardian->phone)) {
            return ['whatsapp'];
        }

        if ($guardian->sms_opt_in && filled($guardian->phone)) {
            return ['sms'];
        }

        if ($guardian->email_opt_in && filled($guardian->email)) {
            return ['email'];
        }

        return null;
    }

    public function render(string $body, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $body = str_replace('{{'.$key.'}}', (string) $value, $body);
            }
        }

        return $body;
    }

    protected function alreadyQueued(Student $student, Guardian $guardian, string $eventType, array $vars, string $channel): bool
    {
        $sessionId = $vars['class_session_id'] ?? null;

        if (! $sessionId) {
            return false;
        }

        return NotificationOutbox::query()
            ->where('student_id', $student->id)
            ->where('guardian_id', $guardian->id)
            ->where('event_type', $eventType)
            ->where('channel', $channel)
            ->where('payload->class_session_id', $sessionId)
            ->whereIn('status', ['pending', 'sent'])
            ->exists();
    }

    protected function resolveTemplate(string $key, string $channel, ?int $tenantId): ?NotificationTemplate
    {
        return NotificationTemplate::query()
            ->withoutGlobalScopes()
            ->where('key', $key)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->orderByRaw('tenant_id is null')
            ->first();
    }

    protected function fallbackBody(string $key): string
    {
        return match ($key) {
            'attendance.absent' => '{{student_name}} was marked ABSENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).',
            'attendance.present' => '{{student_name}} was marked PRESENT for {{batch_name}} on {{date}} ({{subject}}{{topic_part}}).',
            'announcement' => '{{title}}: {{body}}',
            default => '{{message}}',
        };
    }
}

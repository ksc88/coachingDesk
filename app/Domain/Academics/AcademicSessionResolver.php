<?php

namespace App\Domain\Academics;

use App\Models\AcademicSession;
use Illuminate\Support\Carbon;

class AcademicSessionResolver
{
    /**
     * The session that covers today, creating it when a coaching has rolled into a new year.
     * Self-healing so nobody has to remember to switch sessions on 1 April.
     */
    public function current(int $tenantId, ?Carbon $on = null): AcademicSession
    {
        $on = $on ?? Carbon::today();
        $label = $this->labelFor($on);

        $session = AcademicSession::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('name', $label)
            ->first();

        if (! $session) {
            [$startsOn, $endsOn] = $this->rangeFor($label);

            $session = AcademicSession::query()->create([
                'tenant_id' => $tenantId,
                'name' => $label,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'is_current' => true,
            ]);
        }

        if (! $session->is_current) {
            $session->update(['is_current' => true]);
        }

        AcademicSession::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereKeyNot($session->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        return $session;
    }

    public function labelFor(?Carbon $on = null): string
    {
        $on = $on ?? Carbon::today();
        $startYear = $on->month >= 4 ? $on->year : $on->year - 1;

        return $startYear.'-'.substr((string) ($startYear + 1), -2);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function rangeFor(string $label): array
    {
        $startYear = (int) substr($label, 0, 4);

        return [$startYear.'-04-01', ($startYear + 1).'-03-31'];
    }
}

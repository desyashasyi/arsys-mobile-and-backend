<?php

namespace App\Http\Controllers\Specialization;

use App\Http\Controllers\Controller;
use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantDefense;
use Carbon\Carbon;
use Illuminate\Http\Request;

use function Spatie\LaravelPdf\Support\pdf;

class PreDefensePdfController extends Controller
{
    public function export(Request $request, int $id)
    {
        $programId = auth()->user()->staff?->program_id;

        $event = Event::where('id', $id)
            ->where('program_id', $programId)
            ->whereHas('type', fn($q) => $q->where('code', 'PRE'))
            ->with(['type', 'program'])
            ->firstOrFail();

        $formattedDate = Carbon::parse($event->event_date)->format('dmy');
        $eventLabel    = 'PRE-' . $formattedDate . '-' . $event->id;

        $participants = EventApplicantDefense::where('event_id', $id)
            ->with([
                'research.student.program',
                'research.supervisor.staff',
                'defenseExaminer.staff',
                'space',
                'session',
            ])
            ->orderBy('session_id')
            ->orderBy('space_id')
            ->get()
            ->map(fn($p) => [
                'no'             => null,
                'student_number' => $p->research?->student?->nim ?? '-',
                'student_name'   => trim(($p->research?->student?->first_name ?? '') . ' ' . ($p->research?->student?->last_name ?? '')),
                'title'          => $p->research?->title ?? '-',
                'space'          => $p->space?->code ?? '-',
                'session'        => $p->session?->time ?? '-',
                'supervisors'    => $p->research?->supervisor->sortBy('order')->map(fn($s) => trim(($s->staff?->first_name ?? '') . ' ' . ($s->staff?->last_name ?? '')))->implode(', ') ?: '-',
                'examiners'      => $p->defenseExaminer->map(fn($e) => trim(($e->staff?->first_name ?? '') . ' ' . ($e->staff?->last_name ?? '')))->implode(', ') ?: '-',
                'publish'        => $p->publish,
            ])->values()->map(function ($item, $index) {
                $item['no'] = $index + 1;
                return $item;
            });

        $filename = $eventLabel . '.pdf';

        return pdf()
            ->view('pdf.pre-defense-schedule', compact('event', 'eventLabel', 'participants'))
            ->withBrowsershot(fn($b) => $b->setChromePath('/usr/bin/chromium')->noSandbox())
            ->format('a4')
            ->landscape()
            ->name($filename)
            ->inline();
    }
}

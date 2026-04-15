<?php

namespace App\Http\Controllers\Specialization;

use App\Http\Controllers\Controller;
use App\Models\ArSys\Event;
use App\Models\ArSys\FinalDefenseRoom;
use Carbon\Carbon;
use Illuminate\Http\Request;

use function Spatie\LaravelPdf\Support\pdf;

class FinalDefenseRoomsPdfController extends Controller
{
    public function export(Request $request, int $id)
    {
        $programId = auth()->user()->staff?->program_id;

        $event = Event::where('id', $id)
            ->where('program_id', $programId)
            ->whereHas('type', fn($q) => $q->where('code', 'PUB'))
            ->with(['type', 'program'])
            ->firstOrFail();

        $eventLabel = 'PUB-' . Carbon::parse($event->event_date)->format('dmy') . '-' . $event->id;

        $rooms = FinalDefenseRoom::where('event_id', $id)
            ->with(['space', 'session', 'moderator', 'examiner.staff', 'applicant.research.student'])
            ->get()
            ->map(fn($r, $i) => [
                'index'        => $i + 1,
                'space'        => $r->space?->code ?? '-',
                'session'      => $r->session ? $r->session->time . ($r->session->day ? ' (' . $r->session->day . ')' : '') : '-',
                'moderator'    => $r->moderator ? trim($r->moderator->first_name . ' ' . $r->moderator->last_name) : '-',
                'examiners'    => $r->examiner->map(fn($e) => trim(($e->staff?->first_name ?? '') . ' ' . ($e->staff?->last_name ?? '')))->filter()->implode(', ') ?: '-',
                'participants' => $r->applicant->map(fn($p, $pi) => [
                    'no'     => $pi + 1,
                    'number' => $p->research?->student?->nim ?? '-',
                    'name'   => trim(($p->research?->student?->first_name ?? '') . ' ' . ($p->research?->student?->last_name ?? '')),
                    'title'  => $p->research?->title ?? '-',
                    'publish'=> $p->publish,
                ])->values(),
            ]);

        $filename = $eventLabel . '.pdf';

        return pdf()
            ->view('pdf.final-defense-rooms', compact('event', 'eventLabel', 'rooms'))
            ->withBrowsershot(fn($b) => $b->setChromePath('/usr/bin/chromium')->noSandbox())
            ->format('a4')
            ->landscape()
            ->name($filename)
            ->inline();
    }
}

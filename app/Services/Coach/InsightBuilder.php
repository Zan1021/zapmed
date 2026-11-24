<?php

namespace App\Services\Coach;

use App\Models\ActivityEntry;
use App\Models\CoachProfile;
use App\Models\DoseEntry;
use App\Models\InsightEntry;
use App\Models\MealEntry;
use App\Models\SideEffectEntry;
use App\Models\User;
use App\Models\WaterEntry;
use App\Models\WeightEntry;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Insights (build guide 5.13): a daily headline written from the member's own data, "What we noticed", one small goal
 * for today, the "How are you feeling today?" check-in, thumbs feedback, and a weekly summary.
 *
 * Rule based (config coach.insights holds the wording). General information only: never names a medicine, never
 * advises a dose change, and every screen carries the "not medical advice" footer. PROVISIONAL until Amy and the doctor
 * review the rules. Saying "Worse" tells the assigned coach (a message with no health detail, when enabled) and the
 * coach board shows it; the member is only told "your coach has been told" when a coach is really assigned.
 */
class InsightBuilder
{
    public function __construct(
        private readonly CoachAccess $access,
        private readonly MealLogger $meals,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    // --- Daily -----------------------------------------------------------------------------------------------------

    /**
     * @return array{rule:string,headline:string,text:string,levelPercent:?int,noticed:array<int,string>,goal:array{text:string,label:string,url:string},cycle:?array{text:string}}
     */
    public function daily(User $user): array
    {
        $profile = CoachProfile::where('user_id', $user->id)->first();
        $weight = WeightStats::forUser($user);
        $med = MedicationLevel::forUser($user);
        $today = CoachClock::today();

        [$rule, $vars] = $this->pickRule($user, $weight, $med, $today);
        [$headline, $text] = config("coach.insights.headlines.{$rule}");

        return [
            'rule' => $rule,
            'headline' => $headline,
            'text' => strtr($text, $vars),
            'levelPercent' => $med->levelNowPercent,
            'noticed' => $this->noticed($user, $weight),
            'goal' => $this->goal($user, $weight, $today),
            'cycle' => $this->cycleCard($profile),
        ];
    }

    /** @return array{0:string,1:array<string,string>} */
    private function pickRule(User $user, WeightStats $weight, MedicationLevel $med, CarbonImmutable $today): array
    {
        $recentDays = (int) config('coach.insights.recent_symptom_days', 3);
        $effect = SideEffectEntry::where('user_id', $user->id)->orderByDesc('logged_on')->orderByDesc('id')->first();

        if ($effect) {
            $ago = CoachClock::daysBetween($effect->logged_on, $today);

            if ($ago <= 1 && $effect->flagged) {
                return ['tough_symptom', []];
            }
            if ($ago <= $recentDays) {
                return ['symptom_recent', [
                    '{symptom}' => mb_strtolower($effect->symptom),
                    '{when}' => $ago === 0 ? 'today' : ($ago === 1 ? 'yesterday' : "{$ago} days ago"),
                ]];
            }
        }

        if ($med->lastDoseAt) {
            $sinceDose = CoachClock::daysBetween($med->lastDoseAt, $today);

            if ($med->daysToNext !== null && $med->daysToNext <= 0) {
                return ['dose_day', []];
            }
            if ($sinceDose >= 1 && $sinceDose <= 2) {
                return ['after_dose', []];
            }
        }

        $limit = (int) config('coach.insights.weigh_in_days', 7);
        if ($weight->lastLoggedOn && CoachClock::daysBetween($weight->lastLoggedOn, $today) >= $limit) {
            return ['weigh_in', []];
        }

        return ['steady', []];
    }

    /** @return array<int,string> up to four plain facts from the last 7 days */
    private function noticed(User $user, WeightStats $weight): array
    {
        $lines = [];

        if ($weight->weekLossKg !== null) {
            $kg = number_format(abs($weight->weekLossKg), 1);
            $lines[] = $weight->weekLossKg > 0 ? "Your weight is down {$kg} kg over the last 7 days."
                : ($weight->weekLossKg < 0 ? "Your weight is up {$kg} kg over the last 7 days. Day-to-day changes are normal." : 'Your weight is steady over the last 7 days.');
        }

        $waterDays = 0;
        $mealDays = 0;
        $goal = (int) config('coach.targets.water_ml');
        for ($i = 0; $i < 7; $i++) {
            [$from, $to] = $this->dayRange($i);
            if ($goal > 0 && (int) WaterEntry::where('user_id', $user->id)->where('logged_at', '>=', $from)->where('logged_at', '<', $to)->sum('ml') >= $goal) {
                $waterDays++;
            }
            if (MealEntry::where('user_id', $user->id)->where('logged_at', '>=', $from)->where('logged_at', '<', $to)->exists()) {
                $mealDays++;
            }
        }

        $lines[] = "You reached your water goal on {$waterDays} of the last 7 days.";
        $lines[] = $mealDays > 0 ? "You logged meals on {$mealDays} of the last 7 days." : 'You have not logged a meal in the last 7 days. Even one a day helps.';

        return array_slice($lines, 0, 4);
    }

    /** @return array{text:string,label:string,url:string} one small action for today */
    private function goal(User $user, WeightStats $weight, CarbonImmutable $today): array
    {
        $g = config('coach.insights.goals');
        $limit = (int) config('coach.insights.weigh_in_days', 7);
        $totals = $this->meals->todayTotals($user);

        if (! $weight->lastLoggedOn || CoachClock::daysBetween($weight->lastLoggedOn, $today) >= $limit) {
            return ['text' => $g['weigh'][0], 'label' => $g['weigh'][1], 'url' => route('coach.weight.log')];
        }
        if ($totals['count'] === 0) {
            return ['text' => $g['meal'][0], 'label' => $g['meal'][1], 'url' => route('coach.nutrition.log')];
        }

        $hour = (int) CoachClock::now()->format('G');
        $water = app(WaterLogger::class)->todayTotal($user);
        if ($hour >= (int) config('coach.insights.water_nudge_after_hour', 14) && $water < (int) config('coach.targets.water_ml') / 2) {
            return ['text' => $g['water'][0], 'label' => $g['water'][1], 'url' => route('coach.water')];
        }
        if ($totals['protein_g'] < (float) config('coach.targets.protein_g') * 0.6 && $hour >= 15) {
            return ['text' => $g['protein'][0], 'label' => $g['protein'][1], 'url' => route('coach.nutrition.log', ['slot' => 'snack'])];
        }

        return ['text' => $g['walk'][0], 'label' => $g['walk'][1], 'url' => route('coach.activity.log')];
    }

    /** The cycle and weight card (women with the tracker on). */
    private function cycleCard(?CoachProfile $profile): ?array
    {
        $status = CycleCalendar::status($profile);
        if (! $status) {
            return null;
        }

        $sentence = config("coach.cycle.phase_text.{$status['phase']}.weight");

        return ['text' => "Cycle day {$status['day']}, {$status['label']}. {$sentence}"];
    }

    // --- Check-in and feedback ---------------------------------------------------------------------------------------

    public function todayEntry(User $user): ?InsightEntry
    {
        return InsightEntry::where('user_id', $user->id)->whereDate('entry_date', CoachClock::today()->toDateString())->first();
    }

    /**
     * Save today's answer. Returns the reply shown to the member.
     *
     * @throws InvalidArgumentException when the answer is not one of the four
     */
    public function answerCheckin(User $user, string $answer): string
    {
        if (! array_key_exists($answer, config('coach.insights.answers', []))) {
            throw new InvalidArgumentException('Pick one of the answers.');
        }

        $entry = $this->record($user);
        $entry->update(['answer' => $answer]);

        if ($answer === 'worse') {
            return $this->tellCoach($user, $entry);
        }

        return (string) config("coach.insights.answer_replies.{$answer}");
    }

    /** The reply for the answer already saved today (shown again after a reload). */
    public function replyFor(User $user, ?InsightEntry $entry): ?string
    {
        if (! $entry?->answer) {
            return null;
        }

        return $entry->answer === 'worse' ? $this->worseReply($user) : (string) config("coach.insights.answer_replies.{$entry->answer}");
    }

    /** Thumbs up or down. Tapping the same one again clears it. */
    public function feedback(User $user, bool $helpful): InsightEntry
    {
        $entry = $this->record($user);
        $entry->update(['helpful' => $entry->helpful === $helpful ? null : $helpful]);

        return $entry;
    }

    private function tellCoach(User $user, InsightEntry $entry): string
    {
        $coachId = $this->access->assignedCoachId($user);

        if ($coachId && ! $entry->coach_told_at) {
            $entry->update(['coach_told_at' => now()]);

            if (config('coach.insights.notify_enabled') && ($coach = User::find($coachId))) {
                try {
                    $member = $user->member_number ?? '#'.$user->id;
                    $this->dispatcher->send(new OutboundMessage(
                        templateKey: 'coach.checkin_worse', category: 'transactional', user: $coach, phone: $coach->phone,
                        subject: 'Zapmed coach app: member check-in',
                        body: "A member ({$member}) said they feel worse today. Please open your coach board. No health detail is sent by message.",
                        meta: ['patient_id' => $user->id],
                    ), ['email']);
                } catch (\Throwable $e) {
                    Log::error('insights: coach message failed', ['user' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return $this->worseReply($user);
    }

    private function worseReply(User $user): string
    {
        $coachId = $this->access->assignedCoachId($user);
        $coach = $coachId ? User::find($coachId) : null;

        return $coach
            ? strtr((string) config('coach.insights.worse_reply_with_coach'), ['{coach}' => $coach->first_name])
            : (string) config('coach.insights.worse_reply_no_coach');
    }

    private function record(User $user): InsightEntry
    {
        // whereDate: the date column carries a time part, so a plain equality would miss it.
        return $this->todayEntry($user)
            ?? InsightEntry::create(['user_id' => $user->id, 'entry_date' => CoachClock::today()->toDateString()]);
    }

    // --- Weekly ----------------------------------------------------------------------------------------------------

    /**
     * The last 7 days (today included).
     *
     * @return array{from:CarbonImmutable,to:CarbonImmutable,headline:string,entries:int,weightChangeKg:?float,wins:array<int,array{label:string,done:bool,detail:string}>}
     */
    public function weekly(User $user): array
    {
        $to = CoachClock::today();
        $from = $to->subDays(6);
        $range = [$from->utc(), $to->addDay()->utc()];
        $goal = (int) config('coach.targets.water_ml');

        $doses = DoseEntry::where('user_id', $user->id)->where('taken_at', '>=', $range[0])->where('taken_at', '<', $range[1])->count();
        $weights = WeightEntry::where('user_id', $user->id)->whereDate('logged_on', '>=', $from->toDateString())->whereDate('logged_on', '<=', $to->toDateString())->orderBy('logged_on')->get();
        $effects = SideEffectEntry::where('user_id', $user->id)->whereDate('logged_on', '>=', $from->toDateString())->whereDate('logged_on', '<=', $to->toDateString())->count();
        $checkins = InsightEntry::where('user_id', $user->id)->whereDate('entry_date', '>=', $from->toDateString())->whereDate('entry_date', '<=', $to->toDateString())->whereNotNull('answer')->count();
        $mealCount = MealEntry::where('user_id', $user->id)->where('logged_at', '>=', $range[0])->where('logged_at', '<', $range[1])->count();
        $waterCount = WaterEntry::where('user_id', $user->id)->where('logged_at', '>=', $range[0])->where('logged_at', '<', $range[1])->count();
        $activityCount = ActivityEntry::where('user_id', $user->id)->where('logged_at', '>=', $range[0])->where('logged_at', '<', $range[1])->count();

        $mealDays = 0;
        $waterDays = 0;
        for ($i = 0; $i < 7; $i++) {
            [$a, $b] = $this->dayRange($i);
            if (MealEntry::where('user_id', $user->id)->where('logged_at', '>=', $a)->where('logged_at', '<', $b)->exists()) {
                $mealDays++;
            }
            if ($goal > 0 && (int) WaterEntry::where('user_id', $user->id)->where('logged_at', '>=', $a)->where('logged_at', '<', $b)->sum('ml') >= $goal) {
                $waterDays++;
            }
        }

        $change = $weights->count() >= 2 ? round((float) $weights->first()->weight_kg - (float) $weights->last()->weight_kg, 1) : null;

        $wins = [
            ['label' => 'Dose logged', 'done' => $doses > 0, 'detail' => $doses > 0 ? "{$doses} logged" : 'None this week'],
            ['label' => 'Symptom check-ins', 'done' => $checkins > 0, 'detail' => $checkins > 0 ? "{$checkins} of 7 days" : 'None this week'],
            ['label' => 'Weigh-ins', 'done' => $weights->isNotEmpty(), 'detail' => $weights->isNotEmpty() ? $weights->count().' logged' : 'None this week'],
            ['label' => 'Meals logged', 'done' => $mealDays > 0, 'detail' => $mealDays > 0 ? "{$mealDays} of 7 days" : 'None this week'],
            ['label' => 'Water goal met', 'done' => $waterDays > 0, 'detail' => $waterDays > 0 ? "{$waterDays} of 7 days" : 'Not yet this week'],
        ];

        $done = count(array_filter($wins, fn ($w) => $w['done']));
        $headline = config('coach.insights.headlines_weekly.'.($done >= 4 ? 'strong' : ($done >= 2 ? 'steady' : 'start')));

        return [
            'from' => $from, 'to' => $to, 'headline' => $headline,
            'entries' => $doses + $weights->count() + $effects + $checkins + $mealCount + $waterCount + $activityCount,
            'weightChangeKg' => $change, 'wins' => $wins,
        ];
    }

    /** UTC [start, end) of the local day $daysAgo days back. */
    private function dayRange(int $daysAgo): array
    {
        $start = CoachClock::today()->subDays($daysAgo);

        return [$start->utc(), $start->addDay()->utc()];
    }
}

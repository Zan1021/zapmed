<?php

namespace App\Livewire\Doctor;

use App\Models\DoctorAvailability;
use App\Models\DoctorBlockedDate;
use App\Models\DoctorProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ManageAvailability extends Component
{
    // Add time range form
    public int $selectedDay = 1; // Monday by default
    public string $rangeStartTime = '09:00';
    public string $rangeEndTime = '17:00';

    // Blocked date form
    public string $blockedDate = '';
    public string $blockedReason = '';

    public function mount(): void
    {
        $this->blockedDate = now()->addDay()->format('Y-m-d');
    }

    /**
     * Add a time range for a specific day. Auto-generates 15-minute slots.
     */
    public function addTimeRange(): void
    {
        $this->validate([
            'selectedDay' => 'required|integer|between:0,6',
            'rangeStartTime' => 'required|date_format:H:i',
            'rangeEndTime' => 'required|date_format:H:i|after:rangeStartTime',
        ]);

        $profile = $this->getDoctorProfile();

        $start = Carbon::createFromFormat('H:i', $this->rangeStartTime);
        $end = Carbon::createFromFormat('H:i', $this->rangeEndTime);

        $slotsCreated = 0;

        while ($start->copy()->addMinutes(15) <= $end) {
            $slotStart = $start->format('H:i');
            $slotEnd = $start->copy()->addMinutes(15)->format('H:i');

            DoctorAvailability::firstOrCreate(
                [
                    'doctor_profile_id' => $profile->id,
                    'day_of_week' => $this->selectedDay,
                    'start_time' => $slotStart,
                ],
                [
                    'end_time' => $slotEnd,
                    'is_active' => true,
                ]
            );

            $start->addMinutes(15);
            $slotsCreated++;
        }

        session()->flash('success', "Added {$slotsCreated} slots for " . DoctorAvailability::DAY_NAMES[$this->selectedDay] . '.');
    }

    /**
     * Remove all slots for a specific day.
     */
    public function removeDaySlots(int $dayOfWeek): void
    {
        $profile = $this->getDoctorProfile();

        $profile->availabilities()
            ->where('day_of_week', $dayOfWeek)
            ->delete();

        session()->flash('success', 'Removed all slots for ' . DoctorAvailability::DAY_NAMES[$dayOfWeek] . '.');
    }

    /**
     * Remove a specific time range (group of consecutive slots) for a day.
     */
    public function removeTimeRange(int $dayOfWeek, string $startTime, string $endTime): void
    {
        $profile = $this->getDoctorProfile();

        $profile->availabilities()
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '>=', $startTime)
            ->where('start_time', '<', $endTime)
            ->delete();

        session()->flash('success', "Removed {$startTime} - {$endTime} from " . DoctorAvailability::DAY_NAMES[$dayOfWeek] . '.');
    }

    /**
     * Add a blocked date.
     */
    public function addBlockedDate(): void
    {
        $this->validate([
            'blockedDate' => 'required|date|after_or_equal:today',
            'blockedReason' => 'nullable|string|max:255',
        ]);

        $profile = $this->getDoctorProfile();

        DoctorBlockedDate::firstOrCreate(
            [
                'doctor_profile_id' => $profile->id,
                'blocked_date' => $this->blockedDate,
            ],
            [
                'reason' => $this->blockedReason ?: null,
            ]
        );

        $this->blockedReason = '';
        session()->flash('success', 'Blocked date added successfully.');
    }

    /**
     * Remove a blocked date.
     */
    public function removeBlockedDate(int $id): void
    {
        $profile = $this->getDoctorProfile();

        $profile->blockedDates()->where('id', $id)->delete();

        session()->flash('success', 'Blocked date removed.');
    }

    /**
     * Get availability grouped by day with time ranges.
     */
    public function getAvailabilityByDayProperty(): array
    {
        $profile = $this->getDoctorProfile();

        $slots = $profile->availabilities()
            ->active()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $grouped = [];

        foreach (DoctorAvailability::DAY_NAMES as $day => $name) {
            $daySlots = $slots->where('day_of_week', $day);

            if ($daySlots->isEmpty()) {
                $grouped[$day] = [
                    'name' => $name,
                    'ranges' => [],
                    'slot_count' => 0,
                ];
                continue;
            }

            // Group consecutive slots into ranges
            $ranges = [];
            $rangeStart = null;
            $rangeEnd = null;

            foreach ($daySlots as $slot) {
                $slotStart = substr($slot->start_time, 0, 5);
                $slotEnd = substr($slot->end_time, 0, 5);

                if ($rangeStart === null) {
                    $rangeStart = $slotStart;
                    $rangeEnd = $slotEnd;
                } elseif ($slotStart === $rangeEnd) {
                    $rangeEnd = $slotEnd;
                } else {
                    $ranges[] = ['start' => $rangeStart, 'end' => $rangeEnd];
                    $rangeStart = $slotStart;
                    $rangeEnd = $slotEnd;
                }
            }

            if ($rangeStart !== null) {
                $ranges[] = ['start' => $rangeStart, 'end' => $rangeEnd];
            }

            $grouped[$day] = [
                'name' => $name,
                'ranges' => $ranges,
                'slot_count' => $daySlots->count(),
            ];
        }

        return $grouped;
    }

    /**
     * Get blocked dates for this doctor.
     */
    public function getBlockedDatesListProperty()
    {
        return $this->getDoctorProfile()
            ->blockedDates()
            ->where('blocked_date', '>=', today())
            ->orderBy('blocked_date')
            ->get();
    }

    private function getDoctorProfile(): DoctorProfile
    {
        return Auth::user()->doctorProfile;
    }

    public function render()
    {
        return view('livewire.doctor.manage-availability')
            ->layout('layouts.app')
            ->title('My Availability');
    }
}

<?php

use Illuminate\Support\Facades\Schedule;

// Daily SPAR reminder run (spec FR-12). The command ships with the host; the
// standalone reminder command wraps the package SparReminderService.
Schedule::command('spar:process-reminders')->dailyAt('08:00');

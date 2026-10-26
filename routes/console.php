<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('leave:accrue')->monthlyOn(1, '01:00');
Schedule::command('leave:carry-forward')->yearlyOn(1, 1, '02:00');

// Holidays rarely change; weekly catches newly gazetted ones. Admins can also "Sync now".
Schedule::command('holidays:sync')->weeklyOn(1, '03:00')->withoutOverlapping();

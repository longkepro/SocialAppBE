<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Tự động cào tin mới 1 ngày 1 lần vào lúc 06:00 sáng, không chạy chồng chéo
        $schedule->command('news:crawl')->dailyAt('06:00')->withoutOverlapping();
    }

    /**
     * Chỉ định múi giờ cho Scheduler (Giờ Việt Nam UTC+7)
     */
    protected function scheduleTimezone()
    {
        return 'Asia/Ho_Chi_Minh';
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

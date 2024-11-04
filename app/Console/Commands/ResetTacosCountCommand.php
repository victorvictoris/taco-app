<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetTacosCountCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tacos:reset-count';
    protected $description = 'Resetuje broj preostalih takosa za sve korisnike na 5.';

    public function handle()
    {
        User::query()->update(['remaining_tacos' => 5]);
        $this->info('Broj takosa resetovan na 5 za sve korisnike.');
    }
}

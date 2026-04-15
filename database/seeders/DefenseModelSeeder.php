<?php

namespace Database\Seeders;

use App\Models\ArSys\DefenseModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefenseModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DefenseModel::updateOrCreate(['code' => 'PRE', 'name' => 'Pre-Defense']);
        DefenseModel::updateOrCreate(['code' => 'PUB', 'name' => 'Final-Defense']);
        DefenseModel::updateOrCreate(['code' => 'SEM', 'name' => 'Seminar']);
    }
}

<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SubscriptionPlan::create(['name' => 'Free', 'price' => 0.00, 'status' => 'active']);
        SubscriptionPlan::create(['name' => 'Basic', 'price' => 15.00, 'status' => 'active']);
        SubscriptionPlan::create(['name' => 'Pro', 'price' => 50.00, 'status' => 'active']);
        SubscriptionPlan::create(['name' => 'Enterprise', 'price' => 200.00, 'status' => 'active']);

    }
}

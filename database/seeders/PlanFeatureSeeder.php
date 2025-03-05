<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class PlanFeatureSeeder extends Seeder
{
    public function run(): void
    {
        // Free Plan
        $freePlan = SubscriptionPlan::where('name', 'Free')->first();
        $freePlan->features()->createMany([
            [
                'feature_name' => 'email_sending',
                'limits' => json_encode(['email_limit' => 1000]),
            ],
            [
                'feature_name' => 'crm_integration',
                'limits' => json_encode(['crm_integration' => false]),
            ],
        ]);

        // Basic Plan
        $basicPlan = SubscriptionPlan::where('name', 'Basic')->first();
        $basicPlan->features()->createMany([
            [
                'feature_name' => 'email_sending',
                'limits' => json_encode(['email_limit' => 5000]),
            ],
            [
                'feature_name' => 'crm_integration',
                'limits' => json_encode(['crm_integration' => false]),
            ],
            [
                'feature_name' => 'ab_testing',
                'limits' => json_encode(['ab_testing_access' => false]),
            ],
        ]);

        // Pro Plan
        $proPlan = SubscriptionPlan::where('name', 'Pro')->first();
        $proPlan->features()->createMany([
            [
                'feature_name' => 'email_sending',
                'limits' => json_encode(['email_limit' => 50000]),
            ],
            [
                'feature_name' => 'crm_integration',
                'limits' => json_encode(['crm_integration' => true]),
            ],
            [
                'feature_name' => 'ab_testing',
                'limits' => json_encode(['ab_testing_access' => true]),
            ],
        ]);

        // Enterprise Plan
        $enterprisePlan = SubscriptionPlan::where('name', 'Enterprise')->first();
        $enterprisePlan->features()->createMany([
            [
                'feature_name' => 'email_sending',
                'limits' => json_encode(['email_limit' => 'unlimited']),
            ],
            [
                'feature_name' => 'crm_integration',
                'limits' => json_encode(['crm_integration' => true]),
            ],
            [
                'feature_name' => 'ab_testing',
                'limits' => json_encode(['ab_testing_access' => true]),
            ],
        ]);
    }
}


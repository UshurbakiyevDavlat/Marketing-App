<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\User;
use App\Services\CampaignEmailService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Campaign $campaign;
    protected User $user;
    protected CampaignEmailService $campaignEmailService;

    /**
     * Create a new job instance.
     */
    public function __construct(Campaign $campaign, User $user, CampaignEmailService $campaignEmailService)
    {
        $this->campaign = $campaign;
        $this->user = $user;
        $this->campaignEmailService = $campaignEmailService;

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->campaignEmailService->sendCampaign($this->campaign, $this->user);

            Log::info('Campaign emails sent successfully', ['campaign_id' => $this->campaign->id]);
        } catch (Exception $e) {
            Log::error('Failed to send campaign emails', [
                'campaign_id' => $this->campaign->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Enums;

enum ActivityLogAction: string
{
    // FoFunnel
    case LeadCreated = 'lead_created';
    case LeadUpdated = 'lead_updated';
    case LeadDeleted = 'lead_deleted';
    case StageMoved = 'stage_moved';
    case PitchingLogged = 'pitching_logged';
    case FollowUpLogged = 'followup_logged';
    case FollowUpUpdated = 'followup_updated';
    case FollowUpDeleted = 'followup_deleted';
    case FollowUpRestored = 'followup_restored';
    case AkadProcessed = 'akad_processed';
    case WonLogged = 'won_logged';
    case ClosingLogged = 'closing_logged';
    case LostLogged = 'lost_logged';

    // FoContact
    case ContactCreated = 'contact_created';
    case ContactUpdated = 'contact_updated';
    case ContactDeleted = 'contact_deleted';

    // FoTarget
    case TargetUpdated = 'target_updated';

    public function label(): string
    {
        return match ($this) {
            self::LeadCreated => 'Lead Created',
            self::LeadUpdated => 'Lead Updated',
            self::LeadDeleted => 'Lead Deleted',
            self::StageMoved => 'Stage Moved',
            self::PitchingLogged => 'Pitching Logged',
            self::FollowUpLogged => 'Follow Up Logged',
            self::FollowUpUpdated => 'Follow Up Updated',
            self::FollowUpDeleted => 'Follow Up Deleted',
            self::FollowUpRestored => 'Follow Up Restored',
            self::AkadProcessed => 'Akad Processed',
            self::WonLogged => 'Won Logged',
            self::ClosingLogged => 'Closing Logged',
            self::LostLogged => 'Lost Logged',
            self::ContactCreated => 'Contact Created',
            self::ContactUpdated => 'Contact Updated',
            self::ContactDeleted => 'Contact Deleted',
            self::TargetUpdated => 'Target Updated',
        };
    }
}

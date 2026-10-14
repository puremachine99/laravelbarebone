<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encrypted (APP_KEY) copy of an OAuth client's secret, so it can be revealed again from /tech/sso.
 * Rotating APP_KEY makes these unreadable — regenerate the secret in that case.
 */
#[Fillable(['client_id', 'secret'])]
#[Hidden(['secret'])]
class OAuthClientSecret extends Model
{
    protected $table = 'oauth_client_secrets';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<OAuthClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id');
    }
}

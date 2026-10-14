<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the SSO audit trail shown in /tech/sso.
 */
#[Fillable(['client_id', 'client_name', 'user_id', 'action', 'ip_address'])]
class OAuthClientAudit extends Model
{
    protected $table = 'oauth_client_audits';

    public const UPDATED_AT = null;

    /**
     * Human labels for the audited actions.
     *
     * @var array<string, string>
     */
    public const ACTIONS = [
        'created' => 'Aplikasi dibuat',
        'updated' => 'Pengaturan diubah',
        'secret_revealed' => 'Secret ditampilkan',
        'secret_regenerated' => 'Secret di-generate ulang',
        'tokens_revoked' => 'Semua sesi dicabut',
        'user_tokens_revoked' => 'Sesi user dicabut',
        'disabled' => 'Aplikasi dinonaktifkan',
        'enabled' => 'Aplikasi diaktifkan',
        'deleted' => 'Aplikasi dihapus',
    ];

    /**
     * Record an action on the given client by the current user.
     */
    public static function record(OAuthClient $client, string $action): self
    {
        return self::create([
            'client_id' => $client->getKey(),
            'client_name' => $client->name,
            'user_id' => auth()->id(),
            'action' => $action,
            'ip_address' => request()->ip(),
        ]);
    }

    public function label(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

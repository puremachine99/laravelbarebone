<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Confidential SSO apps registered before /api/sso/members existed get the client-credentials grant, so they can
     * fetch their member list with their own token. Public (PKCE) apps have no secret and are left alone.
     */
    public function up(): void
    {
        DB::table('oauth_clients')->whereNotNull('secret')->get(['id', 'grant_types'])->each(function (object $client) {
            $grantTypes = json_decode((string) $client->grant_types, true) ?: [];

            if (! in_array('client_credentials', $grantTypes, true)) {
                DB::table('oauth_clients')->where('id', $client->id)
                    ->update(['grant_types' => json_encode([...$grantTypes, 'client_credentials'])]);
            }
        });
    }

    public function down(): void
    {
        DB::table('oauth_clients')->get(['id', 'grant_types'])->each(function (object $client) {
            $grantTypes = json_decode((string) $client->grant_types, true) ?: [];

            DB::table('oauth_clients')->where('id', $client->id)
                ->update(['grant_types' => json_encode(array_values(array_diff($grantTypes, ['client_credentials'])))]);
        });
    }
};

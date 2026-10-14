<?php

namespace App\Livewire\Tech;

use App\Models\Department;
use App\Models\OAuthClient;
use App\Models\OAuthClientAudit;
use App\Models\User;
use App\Services\Sso\SsoClientManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * /tech/sso: register the internal apps that may "Login with SmartID" (Passport authorization-code clients),
 * reveal/rotate their secrets, see who is signed in and revoke sessions. Every write is audited.
 */
#[Title('SSO / Connected Apps')]
class SsoClients extends Component
{
    /**
     * Callback path used by the client-side guide (partials/sso-laravel-guide).
     */
    public const CALLBACK_PATH = '/auth/smartid/callback';

    #[Url]
    public string $tab = 'apps';

    public bool $showForm = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $description = '';

    /**
     * Base URL of the connected app; the callback defaults to {appUrl}/auth/smartid/callback (the path the guide uses).
     */
    public string $appUrl = '';

    /**
     * Advanced: explicit redirect URIs (one per line). Overrides the callback derived from $appUrl when filled.
     */
    public string $redirectUris = '';

    /**
     * false = every active employee may sign in; true = only the picked roles/departments.
     */
    public bool $restrictAccess = false;

    /**
     * "confidential" (backend app, uses a secret) or "public" (SPA/mobile, PKCE only).
     */
    public string $clientType = 'confidential';

    /** @var array<int, string> */
    public array $scopes = ['profile', 'email'];

    public bool $skipAuthorization = false;

    /** @var array<int, string> */
    public array $allowedRoles = [];

    /** @var array<int, int|string> */
    public array $allowedDepartmentIds = [];

    /**
     * Client whose secret / integration guide is shown in the secret modal.
     */
    public ?string $secretClientId = null;

    public ?string $revealedSecret = null;

    public bool $showGuide = false;

    public ?string $guideClientId = null;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    /**
     * @return Collection<int, OAuthClient>
     */
    #[Computed]
    public function clients(): Collection
    {
        return OAuthClient::query()
            ->withCount(['tokens as active_sessions_count' => fn ($query) => $query
                ->where('revoked', false)
                ->where('expires_at', '>', now())])
            ->orderBy('name')
            ->get();
    }

    /**
     * Active (unrevoked, unexpired) tokens: who is currently signed in to which app.
     *
     * @return Collection<int, Token>
     */
    #[Computed]
    public function sessions(): Collection
    {
        $tokens = Passport::token()->newQuery()
            ->with('client:id,name')
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->limit(200)
            ->get();

        // Token::user() is deprecated and resolves its model through the client, so attach users by hand.
        $users = User::query()->whereKey($tokens->pluck('user_id')->filter()->unique())->get(['id', 'name', 'email'])->keyBy('id');

        return $tokens->each(fn (Token $token) => $token->setRelation('user', $users->get($token->user_id)));
    }

    /**
     * @return Collection<int, OAuthClientAudit>
     */
    #[Computed]
    public function audits(): Collection
    {
        return OAuthClientAudit::query()->with('user:id,name')->latest('id')->limit(100)->get();
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function roleOptions(): Collection
    {
        return Role::query()->orderBy('name')->pluck('name');
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function departmentOptions(): Collection
    {
        return Department::query()->orderBy('name')->pluck('name', 'id');
    }

    #[Computed]
    public function guideClient(): ?OAuthClient
    {
        return $this->guideClientId ? OAuthClient::query()->find($this->guideClientId) : null;
    }

    public function create(): void
    {
        $this->authorizeAccess();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $clientId): void
    {
        $this->authorizeAccess();

        $client = OAuthClient::query()->findOrFail($clientId);

        $this->resetForm();
        $this->editingId = $client->getKey();
        $this->name = $client->name;
        $this->description = (string) $client->description;
        $uris = $client->redirect_uris;

        if (count($uris) === 1 && str_ends_with($uris[0], self::CALLBACK_PATH)) {
            $this->appUrl = substr($uris[0], 0, -strlen(self::CALLBACK_PATH));
        } else {
            $this->redirectUris = implode("\n", $uris);
        }

        $this->clientType = $client->confidential() ? 'confidential' : 'public';
        $this->scopes = $client->scopes ?? array_keys(OAuthClient::SCOPES);
        $this->skipAuthorization = $client->skip_authorization;
        $this->allowedRoles = $client->allowed_roles ?? [];
        $this->allowedDepartmentIds = array_map('strval', $client->allowed_department_ids ?? []);
        $this->restrictAccess = $this->allowedRoles !== [] || $this->allowedDepartmentIds !== [];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeAccess();

        $this->appUrl = rtrim(trim($this->appUrl), '/');
        $this->redirectUris = collect(preg_split('/\s+/', trim($this->redirectUris)))->filter()->unique()->implode("\n");

        if (! $this->restrictAccess) {
            $this->allowedRoles = [];
            $this->allowedDepartmentIds = [];
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'appUrl' => ['required_without:redirectUris', 'nullable', 'string', 'max:255'],
            'redirectUris' => ['nullable', 'string'],
            'clientType' => [Rule::in(['confidential', 'public'])],
            'scopes' => ['array'],
            'scopes.*' => [Rule::in(array_keys(OAuthClient::SCOPES))],
            'skipAuthorization' => ['boolean'],
            'allowedRoles' => ['array'],
            'allowedRoles.*' => [Rule::exists('roles', 'name')],
            'allowedDepartmentIds' => ['array'],
            'allowedDepartmentIds.*' => [Rule::exists('departments', 'id')],
        ], messages: [
            'appUrl.required_without' => 'Isi URL aplikasi, mis. https://procurement.smartid.co.id',
        ], attributes: ['redirectUris' => 'redirect URI', 'appUrl' => 'URL aplikasi']);

        if ($this->restrictAccess && $validated['allowedRoles'] === [] && $validated['allowedDepartmentIds'] === []) {
            $this->addError('allowedRoles', 'Pilih minimal satu role atau departemen, atau ubah akses ke "Semua karyawan aktif".');

            return;
        }

        $uris = $this->redirectUris !== '' ? explode("\n", $this->redirectUris) : [$this->appUrl.self::CALLBACK_PATH];
        $field = $this->redirectUris !== '' ? 'redirectUris' : 'appUrl';

        foreach ($uris as $uri) {
            if (! $this->isAllowedRedirectUri($uri)) {
                $this->addError($field, "URL tidak valid: {$uri}. Wajib https:// (http:// hanya untuk localhost / *.test).");

                return;
            }
        }

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'redirect_uris' => $uris,
            'confidential' => $validated['clientType'] === 'confidential',
            'scopes' => $validated['scopes'],
            'skip_authorization' => $validated['skipAuthorization'],
            'allowed_roles' => $validated['allowedRoles'],
            'allowed_department_ids' => array_map('intval', $validated['allowedDepartmentIds']),
        ];

        if ($this->editingId) {
            $this->manager()->update(OAuthClient::query()->findOrFail($this->editingId), $data);
            session()->flash('success', "Pengaturan {$data['name']} disimpan.");
        } else {
            $client = $this->manager()->create($data);
            session()->flash('success', "{$client->name} terdaftar. Ikuti langkah pemasangan di jendela Kredensial.");
            $this->openGuide($client->getKey());

            if ($client->confidential()) {
                $this->revealSecret($client->getKey());
            }
        }

        $this->showForm = false;
        unset($this->clients);
    }

    /**
     * Show the integration guide (client id, secret, .env and code snippets) for an app.
     */
    public function openGuide(string $clientId): void
    {
        $this->authorizeAccess();

        $this->guideClientId = OAuthClient::query()->findOrFail($clientId)->getKey();
        $this->revealedSecret = null;
        $this->secretClientId = null;
        $this->showGuide = true;
        unset($this->guideClient);
    }

    public function revealSecret(string $clientId): void
    {
        $this->authorizeAccess();

        if (! $this->passwordRecentlyConfirmed()) {
            return;
        }

        $client = OAuthClient::query()->findOrFail($clientId);
        $this->revealedSecret = $this->manager()->revealSecret($client);
        $this->secretClientId = $client->getKey();

        if ($this->revealedSecret === null) {
            $this->addError('secret', 'Secret tidak tersimpan atau tidak bisa dibaca (mis. APP_KEY berubah). Generate ulang secret.');
        }

        unset($this->audits);
    }

    public function hideSecret(): void
    {
        $this->revealedSecret = null;
        $this->secretClientId = null;
    }

    public function regenerateSecret(string $clientId): void
    {
        $this->authorizeAccess();

        if (! $this->passwordRecentlyConfirmed()) {
            return;
        }

        $client = OAuthClient::query()->findOrFail($clientId);
        $this->revealedSecret = $this->manager()->regenerateSecret($client);
        $this->secretClientId = $client->getKey();

        session()->flash('success', "Secret {$client->name} diganti. Secret lama langsung tidak berlaku — update .env aplikasi tersebut.");
        unset($this->audits);
    }

    public function toggleEnabled(string $clientId): void
    {
        $this->authorizeAccess();

        $client = OAuthClient::query()->findOrFail($clientId);
        $this->manager()->setEnabled($client, $client->revoked);

        session()->flash('success', $client->revoked ? "{$client->name} dinonaktifkan dan semua sesinya dicabut." : "{$client->name} diaktifkan kembali.");
        unset($this->clients, $this->sessions, $this->audits);
    }

    public function revokeTokens(string $clientId, ?int $userId = null): void
    {
        $this->authorizeAccess();

        $client = OAuthClient::query()->findOrFail($clientId);
        $count = $this->manager()->revokeTokens($client, $userId);

        session()->flash('success', "{$count} sesi di {$client->name} dicabut. User harus login ulang.");
        unset($this->clients, $this->sessions, $this->audits);
    }

    public function delete(string $clientId): void
    {
        $this->authorizeAccess();

        $client = OAuthClient::query()->findOrFail($clientId);
        $this->manager()->delete($client);

        if ($this->guideClientId === $clientId) {
            $this->showGuide = false;
            $this->guideClientId = null;
        }

        session()->flash('error', "{$client->name} dihapus. Aplikasi tersebut tidak bisa login lagi.");
        unset($this->clients, $this->sessions, $this->audits);
    }

    public function render()
    {
        return view('livewire.tech.sso-clients');
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->appUrl = '';
        $this->redirectUris = '';
        $this->restrictAccess = false;
        $this->clientType = 'confidential';
        $this->scopes = ['profile', 'email'];
        $this->skipAuthorization = false;
        $this->allowedRoles = [];
        $this->allowedDepartmentIds = [];
    }

    /**
     * https only, except http on localhost / *.test (Laragon) for local development of the connected app.
     */
    private function isAllowedRedirectUri(string $uri): bool
    {
        if (filter_var($uri, FILTER_VALIDATE_URL) === false || str_contains($uri, '#')) {
            return false;
        }

        $scheme = parse_url($uri, PHP_URL_SCHEME);
        $host = parse_url($uri, PHP_URL_HOST);

        return $scheme === 'https'
            || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true))
            || ($scheme === 'http' && str_ends_with((string) $host, '.test'));
    }

    private function manager(): SsoClientManager
    {
        return app(SsoClientManager::class);
    }

    /**
     * Livewire actions bypass route middleware after the first render, so re-check the permission on every write.
     */
    private function authorizeAccess(): void
    {
        abort_unless(Gate::allows('tech-sso.view'), 403);
    }

    /**
     * Showing or rotating a secret needs a fresh password confirmation (auth.password_timeout), even if the page
     * has been open for hours. Otherwise the user is sent to confirm and back to this page.
     */
    private function passwordRecentlyConfirmed(): bool
    {
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);

        if (time() - $confirmedAt < (int) config('auth.password_timeout', 10800)) {
            return true;
        }

        session()->put('url.intended', route('tech.sso'));
        $this->redirectRoute('password.confirm');

        return false;
    }
}

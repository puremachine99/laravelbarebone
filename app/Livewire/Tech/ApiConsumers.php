<?php

namespace App\Livewire\Tech;

use App\Models\ApiConsumer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * /tech/api-keys: issue a token per website/app that consumes the public OPD & wilayah lookup API, with the
 * websites it may call from. The plain token is shown once, right after it is issued or rotated.
 */
#[Title('API Keys')]
class ApiConsumers extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $contactName = '';

    public string $contactEmail = '';

    /**
     * Websites allowed to call the API, one per line ("app.example.com", "*.example.com", "localhost:3000").
     */
    public string $allowedOrigins = '';

    public bool $allowServerRequests = false;

    public int $rateLimitPerMinute = 120;

    public string $notes = '';

    /**
     * Plain token of the consumer just created or rotated; shown once.
     */
    public ?string $issuedToken = null;

    public ?string $issuedFor = null;

    public bool $showToken = false;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    /**
     * @return Collection<int, ApiConsumer>
     */
    #[Computed]
    public function consumers(): Collection
    {
        return ApiConsumer::query()->with('creator:id,name')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorizeAccess();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $consumerId): void
    {
        $this->authorizeAccess();
        $consumer = ApiConsumer::query()->findOrFail($consumerId);

        $this->resetForm();
        $this->editingId = $consumer->id;
        $this->name = $consumer->name;
        $this->contactName = (string) $consumer->contact_name;
        $this->contactEmail = (string) $consumer->contact_email;
        $this->allowedOrigins = implode("\n", $consumer->allowed_origins ?? []);
        $this->allowServerRequests = $consumer->allow_server_requests;
        $this->rateLimitPerMinute = $consumer->rate_limit_per_minute;
        $this->notes = (string) $consumer->notes;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeAccess();

        $origins = $this->parsedOrigins();

        $this->withValidator(function (Validator $validator) use ($origins) {
            $validator->after(function (Validator $validator) use ($origins) {
                if ($origins === [] && ! $this->allowServerRequests) {
                    $validator->errors()->add('allowedOrigins', 'Isi minimal satu website, atau izinkan request dari server.');
                }

                foreach (preg_split('/[\s,]+/', $this->allowedOrigins, -1, PREG_SPLIT_NO_EMPTY) as $line) {
                    if (ApiConsumer::normalizeOrigin($line) === null) {
                        $validator->errors()->add('allowedOrigins', "Website tidak valid: {$line}");
                    }
                }
            });
        })->validate([
            'name' => ['required', 'string', 'max:255'],
            'contactName' => ['nullable', 'string', 'max:255'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'allowedOrigins' => ['nullable', 'string', 'max:5000'],
            'allowServerRequests' => ['boolean'],
            'rateLimitPerMinute' => ['required', 'integer', 'min:1', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attributes = [
            'name' => $this->name,
            'contact_name' => $this->contactName ?: null,
            'contact_email' => $this->contactEmail ?: null,
            'allowed_origins' => $origins,
            'allow_server_requests' => $this->allowServerRequests,
            'rate_limit_per_minute' => $this->rateLimitPerMinute,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingId) {
            ApiConsumer::query()->findOrFail($this->editingId)->update($attributes);
            session()->flash('success', 'API key diperbarui.');
        } else {
            $consumer = new ApiConsumer($attributes + ['created_by' => auth()->id()]);
            $token = $consumer->issueToken();
            $consumer->save();

            $this->issuedToken = $token;
            $this->issuedFor = $consumer->name;
            $this->showToken = true;
        }

        $this->showForm = false;
        unset($this->consumers);
    }

    public function regenerate(int $consumerId): void
    {
        $this->authorizeAccess();
        $consumer = ApiConsumer::query()->findOrFail($consumerId);

        $token = $consumer->issueToken();
        $consumer->save();

        $this->issuedToken = $token;
        $this->issuedFor = $consumer->name;
        $this->showToken = true;
        unset($this->consumers);
    }

    public function toggleRevoked(int $consumerId): void
    {
        $this->authorizeAccess();
        $consumer = ApiConsumer::query()->findOrFail($consumerId);

        $consumer->update(['revoked_at' => $consumer->isRevoked() ? null : now()]);

        session()->flash('success', $consumer->isRevoked() ? "API key {$consumer->name} dicabut." : "API key {$consumer->name} diaktifkan lagi.");
        unset($this->consumers);
    }

    public function delete(int $consumerId): void
    {
        $this->authorizeAccess();
        ApiConsumer::query()->findOrFail($consumerId)->delete();

        session()->flash('success', 'API key dihapus.');
        unset($this->consumers);
    }

    public function hideToken(): void
    {
        $this->issuedToken = null;
        $this->issuedFor = null;
        $this->showToken = false;
    }

    public function render()
    {
        return view('livewire.tech.api-consumers');
    }

    /**
     * @return list<string>
     */
    private function parsedOrigins(): array
    {
        return collect(preg_split('/[\s,]+/', $this->allowedOrigins, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $origin) => ApiConsumer::normalizeOrigin($origin))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'contactName', 'contactEmail', 'allowedOrigins', 'allowServerRequests', 'rateLimitPerMinute', 'notes']);
        $this->resetValidation();
    }

    /**
     * Livewire actions bypass route middleware after the first render, so re-check the permission on every write.
     */
    private function authorizeAccess(): void
    {
        abort_unless(Gate::allows('tech-api-keys.view'), 403);
    }
}

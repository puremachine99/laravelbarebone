<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AiData\DataQueryService;
use App\Services\AiData\DatasetCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Runs an AI data request from the terminal exactly as the AI Center will: as the given user, with their permissions
 * and department scope, and written to the audit log. For testing the catalog before the AI uses it.
 */
class AiDataQuery extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ai-data:query
        {user : Email or ID of the user to run the request as}
        {request? : JSON request, e.g. {"dataset":"fo_achievement","group_by":["employee"],"all_time":true,"sort":"nilai_closing"}}
        {--catalog : Show the datasets this user may use instead of running a request}';

    /**
     * @var string
     */
    protected $description = 'Run an AI Center data request as a user (testing the AI data catalog)';

    public function handle(DataQueryService $service, DatasetCatalog $catalog): int
    {
        $user = User::query()
            ->where('email', $this->argument('user'))
            ->when(ctype_digit((string) $this->argument('user')), fn ($query) => $query->orWhere('id', (int) $this->argument('user')))
            ->first();

        if (! $user) {
            $this->error('User tidak ditemukan.');

            return self::FAILURE;
        }

        if ($this->option('catalog')) {
            $this->line($this->toJson($catalog->describeFor($user)));

            return self::SUCCESS;
        }

        $request = json_decode((string) $this->argument('request'), true);

        if (! is_array($request)) {
            $this->error('Request harus berupa JSON object yang valid.');

            return self::FAILURE;
        }

        try {
            $this->line($this->toJson($service->run($user, $request)));
        } catch (AuthorizationException $exception) {
            $this->error('Ditolak: '.$exception->getMessage());

            return self::FAILURE;
        } catch (ValidationException $exception) {
            $this->error('Request tidak valid:');

            foreach ($exception->errors() as $field => $messages) {
                $this->line("  {$field}: ".implode(' ', $messages));
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function toJson(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

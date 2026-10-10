<?php

namespace App\Services\AiData;

use App\Models\User;

/**
 * Reads config/ai_datasets.php and decides which datasets a given user may use.
 */
class DatasetCatalog
{
    /**
     * @return array<string, array{label: string, description: string, permission: string, source: class-string<Dataset>, dimensions: array<string, string>, metrics: array<string, string>, filters: array<int, string>, min_group_size?: int}>
     */
    public function all(): array
    {
        return config('ai_datasets.datasets');
    }

    /**
     * @return array{label: string, description: string, permission: string, source: class-string<Dataset>, dimensions: array<string, string>, metrics: array<string, string>, filters: array<int, string>, min_group_size?: int}|null
     */
    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Suspended users fail every permission check (see User::hasPermissionTo), so they are covered here too.
     */
    public function canUse(User $user, string $key): bool
    {
        $dataset = $this->find($key);

        return $dataset !== null
            && $user->isActive()
            && $user->can('manage-ai-center')
            && $user->can($dataset['permission']);
    }

    public function canSeeAllDepartments(User $user): bool
    {
        return $user->can(config('ai_datasets.all_departments_permission'));
    }

    public function minGroupSize(string $key): int
    {
        return $this->find($key)['min_group_size'] ?? config('ai_datasets.min_group_size');
    }

    /**
     * What the AI is told it can ask for: only the datasets this user may use, without internal details.
     *
     * @return array<int, array{skill: string, department: string|null, dataset: string, time_based: bool, label: string, description: string, dimensions: array<string, string>, metrics: array<string, string>, filters: array<int, string>}>
     */
    public function describeFor(User $user): array
    {
        return collect($this->all())
            ->filter(fn (array $dataset, string $key) => $this->canUse($user, $key))
            ->map(fn (array $dataset, string $key) => [
                'skill' => $dataset['skill'],
                'department' => $dataset['department'],
                'dataset' => $key,
                'time_based' => $dataset['time_based'] ?? true,
                'label' => $dataset['label'],
                'description' => $dataset['description'],
                'dimensions' => $dataset['dimensions'],
                'metrics' => $dataset['metrics'],
                'filters' => $dataset['filters'],
            ])
            ->values()
            ->all();
    }
}

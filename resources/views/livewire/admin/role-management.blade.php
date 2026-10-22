<div class="space-y-6">
    <x-nakama.flash />

    <div class="card bg-base-100 border border-base-300 p-6 space-y-6 shadow-sm rounded-2xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-base-content">Role & Permission Settings</h2>
                <p class="text-sm text-base-content/50 mt-1">Configure which modules, sub-menus, and actions each role may use.</p>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                <x-input wire:model.live.debounce.300ms="search" placeholder="Search role name..." icon="o-magnifying-glass" clearable />
                <x-button variant="primary" icon="o-plus" size="btn-sm" label="New Role" wire:click="resetForm(); $set('showRoleModal', true);" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table w-full text-base-content/70" x-data="{ open: null }">
                <thead>
                    <tr class="text-base-content/50 border-b border-base-300">
                        <th class="w-8"></th>
                        <th class="py-3">Role</th>
                        <th class="text-center">Users</th>
                        <th>Access</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>

                @forelse ($roles as $role)
                    @php
                        $summary = $this->roleSummary($role);
                        $isSuperAdmin = $role->name === 'Super Admin';
                    @endphp

                    <tbody class="border-b border-base-300" wire:key="role-{{ $role->id }}">
                        <tr class="hover:bg-base-200/50 cursor-pointer" x-bind:class="open === {{ $role->id }} ? 'bg-primary/5' : ''" x-on:click="open = open === {{ $role->id }} ? null : {{ $role->id }}">
                            <td class="ps-4">
                                <x-icon name="o-chevron-right" class="size-4 text-base-content/40 transition-transform" x-bind:class="open === {{ $role->id }} ? 'rotate-90 text-primary' : ''" />
                            </td>
                            <td class="py-4">
                                <div class="font-semibold text-base-content">{{ $role->name }}</div>
                                @if ($isSuperAdmin)
                                    <div class="text-xs text-base-content/50">System role</div>
                                @elseif ($summary['readOnly'])
                                    <div class="text-xs text-base-content/50">Read-only</div>
                                @endif
                            </td>
                            <td class="text-center tabular-nums text-base-content">{{ $role->users_count }}</td>
                            <td class="min-w-64">
                                @if ($isSuperAdmin)
                                    <div class="text-sm text-base-content">Full access</div>
                                    <div class="text-xs text-base-content/50">Every module, sub-menu, and action</div>
                                @elseif ($summary['granted'] === 0)
                                    <div class="text-sm text-base-content/40">No permissions</div>
                                @else
                                    <div class="text-sm text-base-content">
                                        {{ $summary['moduleCount'] }} {{ \Illuminate\Support\Str::plural('module', $summary['moduleCount']) }}
                                        @if (count($summary['other']) > 0)
                                            <span class="text-base-content/50">+ {{ count($summary['other']) }} {{ \Illuminate\Support\Str::plural('feature', count($summary['other'])) }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <progress class="progress progress-primary w-32 h-1.5" value="{{ $summary['granted'] }}" max="{{ $summary['total'] }}"></progress>
                                        <span class="text-xs text-base-content/50 tabular-nums">{{ $summary['granted'] }} / {{ $summary['total'] }} permissions</span>
                                    </div>
                                @endif
                            </td>
                            <td class="text-right" x-on:click.stop>
                                <div class="flex gap-2 justify-end">
                                    <x-button icon="o-pencil-square" label="Edit" class="btn-ghost btn-xs text-primary" wire:click="edit({{ $role->id }})" />
                                    @if (! $isSuperAdmin)
                                        <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="confirmDelete({{ $role->id }})" aria-label="Delete {{ $role->name }}" />
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Breakdown: what this role can actually do, per module --}}
                        <tr x-show="open === {{ $role->id }}" x-cloak>
                            <td></td>
                            <td colspan="4" class="pb-5 pt-1">
                                @if ($isSuperAdmin)
                                    <p class="text-sm text-base-content/60">Super Admin always has full access to every module, sub-menu, and action. This cannot be changed.</p>
                                @elseif ($summary['granted'] === 0)
                                    <p class="text-sm text-base-content/60">This role has no permissions yet. Use Edit to assign modules.</p>
                                @else
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-5">
                                        @foreach ($summary['groups'] as $group)
                                            <div>
                                                <div class="flex items-center gap-2 pb-1.5 border-b-2 border-primary/30">
                                                    <x-icon name="o-{{ $group['icon'] }}" class="size-4 text-primary" />
                                                    <span class="text-sm font-semibold text-base-content">{{ $group['label'] }}</span>
                                                    <span class="text-xs text-base-content/40">{{ $group['section'] === 'master' ? 'Data Master' : 'Module' }}</span>
                                                    <span class="ms-auto text-xs text-base-content/50 tabular-nums">{{ $group['granted'] }} / {{ $group['total'] }} sub-menus</span>
                                                </div>
                                                <dl class="divide-y divide-base-200">
                                                    @foreach ($group['resources'] as $resource)
                                                        <div class="flex items-baseline justify-between gap-4 py-1.5 text-sm">
                                                            <dt @class(['text-base-content' => $resource['actions'] !== [], 'text-base-content/30 line-through' => $resource['actions'] === []])>{{ $resource['label'] }}</dt>
                                                            <dd class="text-xs text-base-content/60 text-right">
                                                                {{ $resource['actions'] === [] ? 'No access' : implode(' · ', $resource['actions']) }}
                                                            </dd>
                                                        </div>
                                                    @endforeach
                                                </dl>
                                            </div>
                                        @endforeach

                                        @if (count($summary['other']) > 0)
                                            <div>
                                                <div class="flex items-center gap-2 pb-1.5 border-b-2 border-primary/30">
                                                    <span class="text-sm font-semibold text-base-content">Other Features</span>
                                                </div>
                                                <ul class="divide-y divide-base-200">
                                                    @foreach ($summary['other'] as $feature)
                                                        <li class="py-1.5 text-sm text-base-content">{{ $feature }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="5" class="text-center text-base-content/50 py-8">
                                No roles found.
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        <div class="mt-4">
            <x-pagination-footer :paginator="$roles" />
        </div>
    </div>

    <!-- Role Create/Edit Modal -->
    <x-modal wire:model="showRoleModal" title="{{ $isEdit ? 'Edit Role Permissions' : 'Add New Role' }}" subtitle="Enable a module, then choose which sub-menus and actions it allows." box-class="w-11/12 max-w-5xl">
        @php
            // Full class strings on purpose: Tailwind only generates classes it can find literally in the source.
            $sections = [
                'module' => ['title' => 'Application Modules', 'hint' => 'Business modules and their sub-menus', 'band' => 'bg-base-200 border-base-300', 'accent' => 'text-base-content/70', 'badge' => 'bg-base-content/60', 'number' => 1],
                'master' => ['title' => 'Data Master', 'hint' => 'Reference data management', 'band' => 'bg-base-200 border-base-300', 'accent' => 'text-base-content/70', 'badge' => 'bg-base-content/60', 'number' => 2],
            ];
        @endphp

        <form wire:submit.prevent="saveRole" x-data="{ q: '' }" class="space-y-5">
            {{-- Role name + search + select all --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                <x-input label="Role Name" wire:model="roleName" placeholder="e.g. Partnership Executive" required :disabled="$roleName === 'Super Admin'" />
                <label class="input input-bordered flex items-center gap-2 w-full">
                    <x-icon name="o-magnifying-glass" class="size-4 text-base-content/40" />
                    <input type="search" x-model="q" class="grow" placeholder="Search modules or sub-menus..." aria-label="Search modules or sub-menus" />
                </label>
            </div>

            <label class="flex items-center gap-3 p-3 rounded-lg border border-base-300 bg-base-200/50 cursor-pointer">
                <input type="checkbox" wire:model.live="selectAll" class="checkbox checkbox-sm checkbox-primary" :disabled="$wire.roleName === 'Super Admin'" />
                <div>
                    <div class="text-sm font-semibold text-base-content/90">Select All (Full Access)</div>
                    <div class="text-xs text-base-content/50">Grants every module, sub-menu, and action, like Super Admin.</div>
                </div>
            </label>

            <div class="space-y-10 max-h-[58vh] overflow-y-auto pr-1">
                @foreach ($sections as $section => $style)
                    <section class="rounded-2xl border-2 border-base-300 overflow-hidden">
                        {{-- Section banner --}}
                        <header class="flex items-center gap-3 px-4 py-3 border-b-2 {{ $style['band'] }}">
                            <span class="flex size-7 items-center justify-center rounded-full text-sm font-bold text-white {{ $style['badge'] }}">{{ $style['number'] }}</span>
                            <div class="min-w-0 grow">
                                <h3 class="text-sm font-bold uppercase tracking-wider {{ $style['accent'] }}">{{ $style['title'] }}</h3>
                                <p class="text-xs text-base-content/60">{{ $style['hint'] }}</p>
                            </div>
                            <span class="badge badge-sm badge-outline whitespace-nowrap">{{ count($groupsBySection[$section] ?? []) }} modules</span>
                        </header>

                        <div class="p-4 space-y-4 bg-base-200/30">
                            @foreach ($groupsBySection[$section] ?? [] as $group)
                                @php
                                    $groupNames = collect($group['resources'])->flatMap(fn ($resource, $key) => array_map(fn ($action) => "{$key}.{$action}", $resource['actions']))->values()->all();
                                    $viewCount = count($group['resources']);
                                    $groupActions = collect($group['resources'])->flatMap(fn ($resource) => $resource['actions'])->unique()->intersect(array_keys($actionLabels))->values()->all();
                                    $searchText = strtolower($group['label'].' '.$group['description'].' '.collect($group['resources'])->pluck('label')->implode(' '));
                                @endphp

                                <div
                                    x-data="{ open: true, module: @js($group['module']), names: @js($groupNames) }"
                                    x-show="!q || @js($searchText).includes(q.toLowerCase())"
                                    class="rounded-xl border border-base-300 bg-base-100 overflow-hidden shadow-sm"
                                >
                                    {{-- Module header: access toggle + summary --}}
                                    <div class="flex items-center gap-3 p-4 bg-base-200/60">
                                        <input
                                            type="checkbox"
                                            wire:model="rolePermissions"
                                            value="{{ $group['module'] }}"
                                            aria-label="{{ $group['label'] }} module access"
                                            class="checkbox checkbox-primary"
                                            :disabled="$wire.roleName === 'Super Admin'"
                                            x-on:change="$nextTick(() => {
                                                const held = $wire.rolePermissions;
                                                if ($el.checked) {
                                                    $wire.rolePermissions = [...new Set([...held, ...names.filter(n => n.endsWith('.view'))])];
                                                } else {
                                                    $wire.rolePermissions = held.filter(p => !names.includes(p));
                                                }
                                            })"
                                        />
                                        <x-icon name="o-{{ $group['icon'] }}" class="size-6 shrink-0 text-base-content/50" />
                                        <div class="min-w-0 grow">
                                            <div class="text-sm font-semibold text-base-content/90">{{ $group['label'] }}</div>
                                            <div class="text-xs text-base-content/50">{{ $group['description'] }}</div>
                                        </div>
                                        <span class="badge badge-sm badge-ghost whitespace-nowrap" x-text="names.filter(n => n.endsWith('.view') && $wire.rolePermissions.includes(n)).length + ' / {{ $viewCount }} sub-menus'"></span>
                                        <button type="button" class="btn btn-ghost btn-xs btn-circle" x-on:click="open = ! open" :aria-expanded="open" aria-label="Toggle {{ $group['label'] }} sub-menus">
                                            <x-icon name="o-chevron-down" class="size-4 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                                        </button>
                                    </div>

                                    {{-- Sub-menus + actions --}}
                                    <div x-show="open" x-collapse>
                                        <div class="overflow-x-auto">
                                            <table class="table table-sm w-full">
                                                <thead>
                                                    <tr class="text-base-content/50">
                                                        <th class="w-full">Sub-menu</th>
                                                        @foreach ($groupActions as $action)
                                                            <th class="text-center text-xs font-medium whitespace-nowrap">{{ $actionLabels[$action] }}</th>
                                                        @endforeach
                                                        <th class="text-center text-xs font-medium">All</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($group['resources'] as $resourceKey => $resource)
                                                        @php
                                                            $rowNames = array_map(fn ($action) => "{$resourceKey}.{$action}", $resource['actions']);
                                                        @endphp
                                                        <tr x-data="{ row: @js($rowNames) }" x-show="!q || @js(strtolower($resource['label'])).includes(q.toLowerCase()) || @js(strtolower($group['label'])).includes(q.toLowerCase())" class="hover:bg-base-200/40">
                                                            <td class="text-sm font-medium text-base-content/90">
                                                                {{ $resource['label'] }}
                                                                @if ($resource['actions'] === ['view'])
                                                                    <span class="text-[11px] font-normal text-base-content/40 ms-1">page access only</span>
                                                                @endif
                                                            </td>
                                                            @foreach ($groupActions as $action)
                                                                <td class="text-center">
                                                                    @if (in_array($action, $resource['actions'], true))
                                                                        <input
                                                                            type="checkbox"
                                                                            wire:model="rolePermissions"
                                                                            value="{{ $resourceKey }}.{{ $action }}"
                                                                            aria-label="{{ $resource['label'] }}: {{ $actionLabels[$action] }}"
                                                                            class="checkbox checkbox-sm checkbox-primary"
                                                                            :disabled="$wire.roleName === 'Super Admin' || ! $wire.rolePermissions.includes(module)"
                                                                            @if ($action === 'view' && count($resource['actions']) > 1)
                                                                                x-on:change="$nextTick(() => { if (! $el.checked) { $wire.rolePermissions = $wire.rolePermissions.filter(p => ! row.includes(p)); } })"
                                                                            @endif
                                                                        />
                                                                    @else
                                                                        <span class="text-base-content/20">–</span>
                                                                    @endif
                                                                </td>
                                                            @endforeach
                                                            <td class="text-center">
                                                                <input
                                                                    type="checkbox"
                                                                    aria-label="All actions for {{ $resource['label'] }}"
                                                                    class="checkbox checkbox-sm"
                                                                    :checked="row.every(n => $wire.rolePermissions.includes(n))"
                                                                    :disabled="$wire.roleName === 'Super Admin' || ! $wire.rolePermissions.includes(module)"
                                                                    x-on:change="$wire.rolePermissions = $el.checked ? [...new Set([...$wire.rolePermissions, ...row])] : $wire.rolePermissions.filter(p => ! row.includes(p))"
                                                                />
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                {{-- Other features: always last --}}
                <section class="rounded-2xl border-2 border-base-300 overflow-hidden">
                    <header class="flex items-center gap-3 px-4 py-3 border-b-2 bg-base-200 border-base-300">
                        <span class="flex size-7 items-center justify-center rounded-full text-sm font-bold text-white bg-base-content/60">3</span>
                        <div class="min-w-0 grow">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-base-content/70">Other Features</h3>
                            <p class="text-xs text-base-content/60">Standalone features that are not part of a module</p>
                        </div>
                        <span class="badge badge-sm badge-outline whitespace-nowrap">{{ count($otherPermissions) }} features</span>
                    </header>

                    <div class="p-4 bg-base-200/30 grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach ($otherPermissions as $permission => $meta)
                            <label x-show="!q || @js(strtolower($meta['label'].' '.$meta['description'])).includes(q.toLowerCase())" class="flex items-start gap-3 p-3 rounded-xl border border-base-300 bg-base-100 hover:bg-base-200 cursor-pointer shadow-sm">
                                <input type="checkbox" wire:model="rolePermissions" value="{{ $permission }}" class="checkbox checkbox-sm checkbox-primary mt-0.5" :disabled="$wire.roleName === 'Super Admin'" />
                                <x-icon name="o-{{ $meta['icon'] }}" class="size-5 shrink-0 text-base-content/50 mt-0.5" />
                                <div>
                                    <div class="text-sm font-semibold text-base-content/90">{{ $meta['label'] }}</div>
                                    <div class="text-xs text-base-content/50">{{ $meta['description'] }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </section>
            </div>

            <div class="flex items-center justify-between gap-2 pt-4 border-t border-base-300">
                <p class="text-xs text-base-content/50 hidden sm:block">Ticking an action also grants View and the module access it depends on.</p>
                <div class="flex gap-2 ms-auto">
                    <x-button label="Cancel" wire:click="$set('showRoleModal', false)" class="btn-ghost" />
                    <x-button label="Save" type="submit" class="btn-primary" spinner="saveRole" />
                </div>
            </div>
        </form>
    </x-modal>

    <!-- Confirm Delete Modal -->
    <x-nakama.confirm-modal wire:model="showDeleteModal" title="Delete Role?"
        subtitle="Deleting this role may affect the permissions of users who hold it. This cannot be undone."
        confirm="delete" confirm-label="Yes, Delete" cancel-label="Cancel" />
</div>

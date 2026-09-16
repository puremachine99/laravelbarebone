<?php

use App\Models\InvestorDocument;
use App\Services\ImagePreviewGenerator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\uses;
use function Livewire\Volt\usesPagination;

usesPagination();
layout('layouts.app');
uses([\Mary\Traits\Toast::class, \Livewire\WithFileUploads::class]);

state([
    'search' => '',
    'showTrashed' => false,

    'document_id' => null,
    'title' => '',
    'description' => '',
    'documentFile' => null,
    'currentFileName' => '',
    'isEdit' => false,

    'showFormModal' => false,
    'showDeleteModal' => false,
    'deleteTargetId' => null,
    'showPreviewModal' => false,
    'previewDocumentId' => null,
]);

// Halaman ini boleh dilihat siapapun yang punya akses ke modul Funding (FO, Direksi, dst — sama kayak
// halaman Funding lain, dijaga middleware route `can:access-investors`); cuma Admin FO / Super Admin
// yang boleh CRUD (upload/edit/delete/restore).
$canManage = computed(fn () => auth()->user()->hasRole(['Investor Admin', 'Super Admin']));

$updatedSearch = function () {
    $this->resetPage();
};

$documents = computed(function () {
    $search = $this->search;

    return InvestorDocument::query()
        ->with('uploader')
        ->when($search, fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', ['%'.strtolower($search).'%']))
        ->latest('created_at')
        ->paginate(12);
});

$trashedDocuments = computed(function () {
    if (! $this->showTrashed) {
        return collect();
    }

    return InvestorDocument::onlyTrashed()
        ->with('uploader')
        ->orderByDesc('deleted_at')
        ->get();
});

$toggleTrashed = function () {
    abort_unless($this->canManage, 403);

    $this->showTrashed = ! $this->showTrashed;
};

$resetForm = function () {
    $this->reset(['document_id', 'title', 'description', 'documentFile', 'currentFileName', 'isEdit']);
    $this->resetValidation();
};

$showCreate = function () {
    abort_unless($this->canManage, 403);

    $this->resetForm();
    $this->showFormModal = true;
};

$editDocument = function ($id) {
    abort_unless($this->canManage, 403);

    $document = InvestorDocument::findOrFail($id);

    $this->resetForm();
    $this->document_id = $document->id;
    $this->title = $document->title;
    $this->description = $document->description;
    $this->currentFileName = $document->file_name;
    $this->isEdit = true;
    $this->showFormModal = true;
};

// Path disimpan di bawah folder per-environment (investors/investor-docs/{env}/...) biar upload dari dev
// gak pernah nimpa/campur objek yang sama di bucket S3 yang dipakai production.
$saveDocument = function () {
    abort_unless($this->canManage, 403);

    $this->validate([
        'title' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:1000'],
        'documentFile' => [$this->isEdit ? 'nullable' : 'required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,webp', 'max:1024'],
    ], [
        'title.required' => 'Judul dokumen wajib diisi.',
        'documentFile.required' => 'File dokumen wajib diupload.',
        'documentFile.mimes' => 'Format file harus PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, atau gambar (JPG/PNG/GIF/WEBP).',
        'documentFile.max' => 'Ukuran file maksimal 1MB.',
    ]);

    $oldPath = null;
    $oldPreviewPath = null;
    $attributes = [
        'title' => $this->title,
        'description' => $this->description ?: null,
    ];

    if ($this->documentFile) {
        $existing = $this->document_id ? InvestorDocument::find($this->document_id) : null;
        $oldPath = $existing?->file_path;
        $oldPreviewPath = $existing?->preview_path;

        $extension = $this->documentFile->getClientOriginalExtension();
        $storedName = Str::uuid().'.'.$extension;
        $path = $this->documentFile->storeAs('investors/investor-docs/'.app()->environment(), $storedName, 's3');

        $isImage = str_starts_with((string) $this->documentFile->getMimeType(), 'image/');
        $previewPath = $isImage
            ? app(ImagePreviewGenerator::class)->generate($this->documentFile, 'investors/investor-docs/'.app()->environment().'/previews')
            : null;

        $attributes = array_merge($attributes, [
            'file_path' => $path,
            'preview_path' => $previewPath,
            'file_name' => trim($this->title).'.'.$extension,
            'original_file_name' => $this->documentFile->getClientOriginalName(),
            'extension' => $extension,
            'mime_type' => $this->documentFile->getMimeType(),
            'size' => $this->documentFile->getSize(),
        ]);
    } elseif ($this->isEdit) {
        // File gak diganti, tapi judul bisa berubah — nama file (buat download) tetap harus ikut judul terbaru.
        $existing = InvestorDocument::find($this->document_id);
        $attributes['file_name'] = trim($this->title).'.'.$existing?->extension;
    }

    if (! $this->document_id) {
        $attributes['uploaded_by'] = auth()->id();
    }

    InvestorDocument::updateOrCreate(['id' => $this->document_id], $attributes);

    if ($oldPath) {
        Storage::disk('s3')->delete($oldPath);
    }
    if ($oldPreviewPath) {
        Storage::disk('s3')->delete($oldPreviewPath);
    }

    $this->resetForm();
    $this->showFormModal = false;
    $this->success($this->isEdit ? 'Dokumen berhasil diperbarui.' : 'Dokumen berhasil diupload.');
};

$confirmDelete = function ($id) {
    abort_unless($this->canManage, 403);

    $this->deleteTargetId = $id;
    $this->showDeleteModal = true;
};

$deleteDocument = function () {
    abort_unless($this->canManage, 403);

    if ($this->deleteTargetId) {
        InvestorDocument::findOrFail($this->deleteTargetId)->delete();
        $this->success('Dokumen dipindahkan ke Terhapus. Masih bisa dipulihkan lewat "View Deleted".');
    }

    $this->showDeleteModal = false;
    $this->reset(['deleteTargetId']);
};

$restoreDocument = function ($id) {
    abort_unless($this->canManage, 403);

    InvestorDocument::onlyTrashed()->findOrFail($id)->restore();
    $this->success('Dokumen berhasil dipulihkan.');
};

$previewDocument = computed(function () {
    return $this->previewDocumentId ? InvestorDocument::find($this->previewDocumentId) : null;
});

$openPreview = function ($id) {
    $document = InvestorDocument::findOrFail($id);

    if (! $document->isPreviewable()) {
        return;
    }

    $this->previewDocumentId = $id;
    $this->showPreviewModal = true;
};

?>

<div class="space-y-6">
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold text-base-content">Master Document</h2>
                    <span class="badge badge-warning badge-outline badge-sm">Dev — env: {{ app()->environment() }}</span>
                </div>
                <p class="text-sm text-base-content/50 mt-1">
                    Materi pembekalan FO buat pitching — brosur, company profile, power point, dan dokumen pendukung lainnya.
                </p>
                <p class="text-xs text-base-content/40 mt-1">
                    Dev note: file diupload ke folder S3 khusus environment ini (<code class="text-[11px]">investors/investor-docs/{{ app()->environment() }}/</code>), jadi gak akan campur sama data production.
                </p>
            </div>
            @if ($this->canManage)
                <div class="flex items-center gap-2">
                    <x-button icon="o-archive-box" size="btn-sm" :label="$showTrashed ? 'Hide Deleted' : 'View Deleted'" class="btn-ghost {{ $showTrashed ? 'btn-active' : '' }}" wire:click="toggleTrashed" />
                    <x-button variant="primary" icon="o-plus" size="btn-sm" label="Upload Document" wire:click="showCreate" />
                </div>
            @endif
        </div>

        <div class="mt-4 w-full sm:w-72">
            <x-input wire:model.live.debounce.300ms="search" placeholder="Search document title..." icon="o-magnifying-glass" clearable />
        </div>
    </div>

    <!-- Trashed documents -->
    @if ($this->canManage && $showTrashed)
        <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
            <div class="text-sm font-semibold text-base-content mb-1">Deleted Documents</div>
            <p class="text-xs text-base-content/50 mb-4">Still recoverable — files are kept, only hidden from the active list.</p>

            @if ($this->trashedDocuments->isEmpty())
                <div class="text-center text-base-content/50 py-6 text-sm">No deleted documents.</div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach ($this->trashedDocuments as $document)
                        <div class="card bg-base-200/50 border border-base-300 p-4 rounded-xl opacity-70" wire:key="trashed-{{ $document->id }}">
                            <div class="min-w-0">
                                <div class="font-semibold text-sm text-base-content truncate" title="{{ $document->title }}">{{ $document->title }}</div>
                                <span class="badge {{ $document->badgeClasses() }} badge-sm uppercase mt-1">{{ $document->extension }}</span>
                                <div class="text-xs text-base-content/40 mt-1">Deleted {{ $document->deleted_at?->format('d M Y H:i') }}</div>
                            </div>
                            <x-button label="Restore" icon="o-arrow-path" size="btn-xs" class="btn-outline btn-sm w-full mt-3" wire:click="restoreDocument({{ $document->id }})" />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- Active documents grid -->
    <div class="card bg-base-100 border border-base-300 p-6 shadow-sm rounded-2xl">
        @if ($this->documents->isEmpty())
            <div class="text-center text-base-content/50 py-10 text-sm">No documents uploaded yet.</div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach ($this->documents as $document)
                    <div class="card bg-base-100 border border-base-300 p-4 rounded-xl hover:shadow-md transition-shadow" wire:key="doc-{{ $document->id }}">
                        <div class="relative -mt-1 mb-3 aspect-video overflow-visible">
                            <div class="absolute inset-0 rounded-lg overflow-hidden border border-base-300 {{ $document->preview_path ? 'bg-base-200' : $document->placeholderClasses() }}">
                                @if ($document->preview_path)
                                    <img src="{{ $document->previewUrl() }}" alt="{{ $document->title }}" class="w-full h-full object-cover" loading="lazy" />
                                @else
                                    <div class="w-full h-full flex items-center justify-center">
                                        <span class="text-xl font-extrabold tracking-wide">{{ strtoupper($document->extension) }}</span>
                                    </div>
                                @endif
                            </div>

                            @if ($this->canManage)
                                {{-- Sengaja di luar box gambar (bukan di dalamnya) biar popup menu-nya gak ke-clip sama overflow-hidden buat rounded corner gambar di atas --}}
                                <div class="absolute top-1.5 right-1.5 z-50">
                                    <x-dropdown right no-x-anchor>
                                        <x-slot:trigger>
                                            <x-button icon="o-ellipsis-vertical" class="btn-circle btn-xs bg-base-100/80 backdrop-blur hover:bg-base-100 shadow" />
                                        </x-slot:trigger>
                                        <li><a wire:click="editDocument({{ $document->id }})"><x-icon name="o-pencil-square" class="w-4 h-4" /> Edit</a></li>
                                        <li><a wire:click="confirmDelete({{ $document->id }})" class="text-error"><x-icon name="o-trash" class="w-4 h-4" /> Delete</a></li>
                                    </x-dropdown>
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <div class="font-semibold text-sm text-base-content truncate" title="{{ $document->title }}">{{ $document->title }}</div>
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="badge {{ $document->badgeClasses() }} badge-sm uppercase">{{ $document->extension }}</span>
                                <span class="text-[11px] text-base-content/40">{{ $document->humanSize() }}</span>
                            </div>
                        </div>

                        <p class="text-xs text-base-content/50 mt-2 line-clamp-2" title="{{ $document->description }}">
                            {{ $document->description ?: 'No description.' }}
                        </p>

                        <div class="flex items-center justify-between gap-2 mt-3 text-[11px] text-base-content/40">
                            <div class="flex items-center gap-1 min-w-0">
                                <x-icon name="o-user" class="w-3 h-3 shrink-0" />
                                <span class="truncate" title="{{ $document->uploader?->name }}">{{ $document->uploader?->name ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <x-icon name="o-clock" class="w-3 h-3" />
                                <span>{{ $document->created_at?->format('d M Y H:i') }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-base-300">
                            <x-button label="Download" icon="o-arrow-down-tray" size="btn-xs" class="btn-primary btn-outline btn-sm flex-1" link="{{ $document->url() }}" external />
                            <x-button label="Preview" icon="o-eye" size="btn-xs" class="btn-accent btn-outline btn-sm flex-1" wire:click="openPreview({{ $document->id }})" :disabled="! $document->isPreviewable()" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $this->documents->links() }}
            </div>
        @endif
    </div>

    @if ($this->canManage)
        <!-- Upload / Edit Modal -->
        <x-modal wire:model="showFormModal" title="{{ $isEdit ? 'Edit Document' : 'Upload Document' }}" subtitle="Judul akan dipakai sebagai nama file yang dilihat/didownload FO.">
            <form wire:submit.prevent="saveDocument" class="space-y-4">
                <x-input label="Title" wire:model="title" placeholder="e.g. Brosur Produk Reksa Dana A" icon="o-document-text" required />

                <x-textarea label="Description" wire:model="description" placeholder="Short description of what this document is for" rows="3" />

                @if ($isEdit && $currentFileName)
                    <div class="text-xs text-base-content/50">Current file: {{ $currentFileName }}</div>
                @endif
                <x-file label="File" wire:model="documentFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp" :hint="$isEdit ? 'Leave empty to keep the current file. Format PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, or image. Maximum 1MB.' : 'Format PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, or image. Maximum 1MB.'" :required="! $isEdit" />

                <div class="flex justify-end gap-2 pt-4 border-t border-base-300">
                    <x-button label="Cancel" wire:click="$set('showFormModal', false)" class="btn-ghost" />
                    <x-button label="Save" type="submit" class="btn-primary" spinner="saveDocument" />
                </div>
            </form>
        </x-modal>

        <!-- Delete Confirmation Modal -->
        <x-modal wire:model="showDeleteModal" title="Delete Document?" subtitle="The document will be moved to Deleted — it can still be restored later.">
            <div class="flex justify-end gap-2 pt-4">
                <x-button label="Cancel" wire:click="$set('showDeleteModal', false)" class="btn-ghost" />
                <x-button label="Yes, Delete" wire:click="deleteDocument" class="btn-error text-white" />
            </div>
        </x-modal>
    @endif

    <!-- Preview Modal -->
    <x-modal wire:model="showPreviewModal" title="{{ $this->previewDocument?->title }}" box-class="max-w-4xl">
        @if ($this->previewDocument)
            @if (in_array(strtolower($this->previewDocument->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                <img src="{{ $this->previewDocument->inlineUrl() }}" alt="{{ $this->previewDocument->title }}" class="w-full max-h-[75vh] object-contain rounded-lg" />
            @else
                <iframe src="{{ $this->previewDocument->inlineUrl() }}" class="w-full h-[75vh] rounded-lg border border-base-300"></iframe>
            @endif
        @endif
    </x-modal>
</div>

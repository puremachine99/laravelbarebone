<?php

namespace App\Http\Controllers\AiCenter;

use App\Http\Controllers\Controller;
use App\Models\AiFile;
use App\Services\AiCenter\AiFileUploadException;
use App\Services\AiCenter\AiGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files in the AI Center: upload (before a message is sent), remove an upload that was not sent, generate a document from structured data, and
 * download a file from the user's own conversations — theirs or one the AI generated. The AI service's storage is never exposed.
 */
class FileController extends Controller
{
    public function store(Request $request, AiGateway $gateway): JsonResponse
    {
        $maxPerMessage = (int) config('ai_center.files.max_per_message');

        $request->validate([
            'files' => ['required', 'array', 'max:'.$maxPerMessage],
            'files.*' => [
                'file',
                'max:'.(int) config('ai_center.files.max_mb') * 1024,
                'extensions:'.implode(',', config('ai_center.files.extensions')),
            ],
        ], [
            'files.max' => __('Maksimal :max file per pesan.', ['max' => $maxPerMessage]),
            'files.*.max' => __('File terlalu besar (maks :max MB).', ['max' => config('ai_center.files.max_mb')]),
            'files.*.extensions' => __('Jenis file tidak didukung.'),
        ]);

        $user = $request->user();

        if ($user->aiFiles()->pendingUpload()->count() + count($request->file('files')) > $maxPerMessage) {
            return response()->json(['message' => __('Maksimal :max file per pesan.', ['max' => $maxPerMessage])], 422);
        }

        try {
            $uploaded = $gateway->uploadFiles($request->file('files'));
        } catch (AiFileUploadException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode->value], 422);
        }

        $files = collect($uploaded)->map(fn (array $file) => $user->aiFiles()->create([
            'remote_file_id' => $file['id'],
            'origin' => AiFile::ORIGIN_UPLOAD,
            'name' => $file['name'],
            'mime_type' => $file['mime_type'],
            'size_bytes' => $file['size_bytes'],
        ]));

        return response()->json(['files' => $files->map(fn (AiFile $file) => $file->previewPayload())->all()], 201);
    }

    public function generate(Request $request, AiGateway $gateway): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'format' => ['required', 'in:xlsx,docx,pptx,csv,md'],
            'filename' => ['required', 'string', 'min:1', 'max:120'],
            'title' => ['nullable', 'string', 'max:300'],
            'template_file_id' => ['nullable', 'string', 'max:64'],
            'sheets' => ['array', 'max:30'],
            'sections' => ['array', 'max:500'],
            'slides' => ['array', 'max:100'],
            'sources' => ['array', 'max:50'],
        ]);

        if (isset($validated['template_file_id'])) {
            abort_unless($user->aiFiles()->where('remote_file_id', $validated['template_file_id'])->exists(), 404);
        }

        try {
            $generated = $gateway->generateDocument(array_filter($validated, fn ($value) => $value !== null));
        } catch (AiFileUploadException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode->value], 422);
        }

        $file = $user->aiFiles()->create([
            'remote_file_id' => $generated['id'],
            'origin' => AiFile::ORIGIN_GENERATED,
            'name' => $generated['name'],
            'mime_type' => $generated['mime_type'],
            'size_bytes' => $generated['size_bytes'],
        ]);

        return response()->json(['file' => $file->previewPayload()], 201);
    }

    public function destroy(Request $request, AiGateway $gateway, string $remoteFileId): Response
    {
        $file = $request->user()->aiFiles()->pendingUpload()->where('remote_file_id', $remoteFileId)->firstOrFail();

        $gateway->deleteFile($file->remote_file_id);
        $file->delete();

        return response()->noContent();
    }

    public function download(Request $request, AiGateway $gateway, AiFile $aiFile): StreamedResponse
    {
        abort_unless($aiFile->user_id === $request->user()->id && $aiFile->isDownloadable(), 404);

        return $this->streamFile($gateway, $aiFile, 'attachment');
    }

    /**
     * Shows an image or PDF inside the page's own preview iframe. Only raster images and PDFs get here, and the
     * browser's PDF viewer does not run in a sandboxed response, so no CSP sandbox is added.
     */
    public function preview(Request $request, AiGateway $gateway, AiFile $aiFile): StreamedResponse
    {
        abort_unless($aiFile->user_id === $request->user()->id, 404);
        abort_unless(in_array($aiFile->previewKind(), ['image', 'pdf'], true), 415);

        $response = $this->streamFile($gateway, $aiFile, 'inline');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }

    private function streamFile(AiGateway $gateway, AiFile $aiFile, string $disposition): StreamedResponse
    {
        $stream = $gateway->downloadFile($aiFile->remote_file_id);

        abort_if($stream === null, 410, __('File sudah tidak tersedia.'));

        return response()->stream(function () use ($stream) {
            while (! $stream->eof()) {
                echo $stream->read(8192);
            }
        }, 200, [
            'Content-Type' => $aiFile->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $aiFile->name, Str::ascii($aiFile->name)),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use App\Support\PublicUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class KnowledgeBaseController extends Controller
{
    /** Max characters to store from URL or file (avoid huge payloads). */
    private const MAX_CONTENT_LENGTH = 500_000;

    public function index(Request $request): JsonResponse
    {
        $items = KnowledgeBase::visibleTo($request->user())->orderBy('name')->paginate($request->input('per_page', 50));

        return response()->json($items);
    }

    /**
     * Fetch content from a URL and return extracted text (for Knowledge Base import).
     */
    public function fetchUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|starts_with:http://,https://',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $url = $request->input('url');

        $target = PublicUrl::resolve($url);

        if ($target === null) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['url' => ['This address cannot be imported. Use a public web page.']],
            ], 422);
        }

        try {
            // Pinned to the address just checked, and no redirects: either
            // would let the fetch land somewhere other than where it was
            // checked.
            $pinned = $target['host'].':'.$target['port'].':'
                .(str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip']);

            $response = Http::timeout(15)
                ->withoutRedirecting()
                ->withOptions(['curl' => [CURLOPT_RESOLVE => [$pinned]]])
                ->get($url);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Failed to fetch URL',
                    'error' => 'HTTP ' . $response->status(),
                ], 422);
            }

            $body = $response->body();
            $content = $this->extractTextFromHtml($body);

            if (Str::length($content) > self::MAX_CONTENT_LENGTH) {
                $content = Str::limit($content, self::MAX_CONTENT_LENGTH);
            }

            return response()->json(['content' => $content]);
        } catch (\Throwable $e) {
            Log::warning('Knowledge Base fetch URL failed', ['url' => $url, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to fetch URL',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function extractTextFromHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'name' => 'required|string|max:255',
            'content' => 'nullable|string|max:' . self::MAX_CONTENT_LENGTH,
            'file' => 'nullable|file|mimes:txt,md,csv,html|max:10240', // 10MB, text-like only
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $content = $request->input('content');

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $content = $file->get();
            if (Str::length($content) > self::MAX_CONTENT_LENGTH) {
                $content = Str::limit($content, self::MAX_CONTENT_LENGTH);
            }
        }

        $kb = KnowledgeBase::create([
            'user_id' => $request->user()->id,
            'name' => $request->input('name'),
            'content' => $content ?: null,
        ]);

        return response()->json([
            'message' => 'Knowledge Base created successfully',
            'knowledge_base' => $kb,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $kb = KnowledgeBase::visibleTo($request->user())->findOrFail($id);

        return response()->json(['data' => $kb]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $kb = KnowledgeBase::visibleTo($request->user())->findOrFail($id);

        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'content' => 'nullable|string|max:' . self::MAX_CONTENT_LENGTH,
            'file' => 'nullable|file|mimes:txt,md,csv,html|max:10240',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $content = $file->get();
            if (Str::length($content) > self::MAX_CONTENT_LENGTH) {
                $content = Str::limit($content, self::MAX_CONTENT_LENGTH);
            }
            $data['content'] = $content;
            unset($data['file']);
        }

        $kb->update($data);

        return response()->json([
            'message' => 'Knowledge Base updated successfully',
            'knowledge_base' => $kb->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $kb = KnowledgeBase::visibleTo($request->user())->findOrFail($id);
        $kb->delete();

        return response()->json(['message' => 'Knowledge Base deleted successfully']);
    }
}

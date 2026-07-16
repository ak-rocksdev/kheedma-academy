<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContentSectionRequest;
use App\Http\Requests\UpdateContentSectionRequest;
use App\Models\ContentSection;
use App\Support\SectionBodySanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentSectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['required', Rule::in(['community', 'program'])],
            'program_id' => ['required_if:page,program', 'nullable', 'exists:programs,id'],
        ]);

        return response()->json([
            'sections' => $this->pageQuery($data)->orderBy('sort_order')->get()->map($this->payload(...)),
        ]);
    }

    public function store(StoreContentSectionRequest $request, SectionBodySanitizer $sanitizer): JsonResponse
    {
        $data = $request->validated();
        $data['body'] = $sanitizer->sanitize($data['body']);
        $data['sort_order'] = ($this->pageQuery($data)->max('sort_order') ?? -1) + 1;

        $section = ContentSection::create($data);

        return response()->json(['section' => $this->payload($section)], 201);
    }

    public function update(
        UpdateContentSectionRequest $request,
        ContentSection $section,
        SectionBodySanitizer $sanitizer,
    ): JsonResponse {
        $data = $request->validated();
        $data['body'] = $sanitizer->sanitize($data['body']);

        $section->update($data);

        return response()->json(['section' => $this->payload($section)]);
    }

    public function destroy(ContentSection $section): JsonResponse
    {
        $section->delete();

        return response()->json(['ok' => true]);
    }

    /** Persist a full reorder of one page's sections (array index = new sort_order). */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['required', Rule::in(['community', 'program'])],
            'program_id' => ['required_if:page,program', 'prohibited_if:page,community', 'nullable', 'exists:programs,id'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $current = $this->pageQuery($data)->pluck('id');
        if ($current->count() !== count($data['ids']) || $current->diff($data['ids'])->isNotEmpty()) {
            return response()->json(['message' => 'Daftar section tidak cocok dengan isi halaman ini. Muat ulang dulu ya.'], 422);
        }

        foreach (array_values($data['ids']) as $index => $id) {
            ContentSection::whereKey($id)->update(['sort_order' => $index]);
        }

        return response()->json(['ok' => true]);
    }

    /** @param array{page:string,program_id?:int|null} $data */
    private function pageQuery(array $data): Builder
    {
        return ContentSection::query()
            ->where('page', $data['page'])
            ->when($data['page'] === 'program', fn (Builder $q) => $q->where('program_id', $data['program_id']));
    }

    /** @return array{id:int,page:string,program_id:?int,heading:?string,body:string,sort_order:int} */
    private function payload(ContentSection $section): array
    {
        return [
            'id' => $section->id,
            'page' => $section->page,
            'program_id' => $section->program_id,
            'heading' => $section->heading,
            'body' => $section->body,
            'sort_order' => $section->sort_order,
        ];
    }
}

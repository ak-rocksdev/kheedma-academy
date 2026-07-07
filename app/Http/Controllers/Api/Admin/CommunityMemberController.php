<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityMemberController extends Controller
{
    /** Paginated, searchable list of community members (newest join first). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $members = CommunityMembership::query()
            ->with('person:id,name,phone,email')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->whereHas('person', function ($p) use ($term) {
                    $p->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CommunityMembership $m) => [
                'id' => $m->id,
                'joined_at' => $m->created_at?->toIso8601String(),
                'referral_source' => $m->referral_source,
                'person' => [
                    'id' => $m->person->id,
                    'name' => $m->person->name,
                    'phone' => $m->person->phone,
                    'email' => $m->person->email,
                ],
            ]);

        return response()->json($members);
    }
}

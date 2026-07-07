<?php

namespace App\Actions;

use App\Models\Application;
use App\Models\CommunityMembership;
use App\Models\Enrollment;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MergePeople
{
    /**
     * Fields the survivor inherits from the duplicate when its own value is
     * missing. Identity fields (name/phone/email) always stay the survivor's.
     */
    private const BACKFILL_FIELDS = ['province_code', 'city_code', 'tiktok_username', 'instagram_username'];

    /**
     * Dry-run: what would move, and what blocks the merge. Shares the exact
     * conflict checks the merge transaction re-runs, so preview and execution
     * can never disagree on the rules.
     *
     * @return array{can_merge: bool, conflicts: list<string>, moves: array{applications: int, enrollments: int, membership: bool, account: bool}}
     */
    public function preview(Person $survivor, Person $duplicate): array
    {
        $conflicts = $this->conflicts($survivor, $duplicate);

        return [
            'can_merge' => $conflicts === [],
            'conflicts' => $conflicts,
            'moves' => $this->moves($duplicate),
        ];
    }

    /**
     * Repoint the duplicate's history onto the survivor, then tombstone the
     * duplicate: phone/email are mangled to `merged:{id}:{original}` (inert to
     * the +62 regex, invalid as an email, unique by id prefix) so the freed
     * real identity becomes reusable despite the plain unique indexes.
     *
     * @return array{applications: int, enrollments: int, membership: bool, account: bool}
     */
    public function merge(Person $survivor, Person $duplicate): array
    {
        return DB::transaction(function () use ($survivor, $duplicate): array {
            // Lock both rows in id order (deterministic order avoids deadlocks
            // between concurrent merges), then re-run the conflict checks on
            // the locked state — the preview may be stale by now.
            $locked = Person::whereIn('id', [$survivor->id, $duplicate->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $survivor = $locked->get($survivor->id);
            $duplicate = $locked->get($duplicate->id);
            if ($survivor === null || $duplicate === null) {
                throw ValidationException::withMessages([
                    'merge' => 'Data orang berubah sejak halaman dimuat. Muat ulang dan coba lagi.',
                ]);
            }

            $conflicts = $this->conflicts($survivor, $duplicate);
            if ($conflicts !== []) {
                throw ValidationException::withMessages(['merge' => $conflicts]);
            }

            $moves = $this->moves($duplicate);

            // Repoint via UPDATE — never delete+recreate — so appended history
            // (status_events, timestamps) survives untouched.
            Application::where('people_id', $duplicate->id)->update(['people_id' => $survivor->id]);
            Enrollment::where('people_id', $duplicate->id)->update(['people_id' => $survivor->id]);
            CommunityMembership::where('people_id', $duplicate->id)->update(['people_id' => $survivor->id]);

            // people.user_id is unique: release the duplicate's link before the
            // survivor takes it, so no instant exists where two rows hold it.
            if ($duplicate->user_id !== null) {
                $userId = $duplicate->user_id;
                $duplicate->user_id = null;
                $duplicate->save();
                $survivor->user_id = $userId;
            }

            foreach (self::BACKFILL_FIELDS as $field) {
                if ($survivor->{$field} === null && $duplicate->{$field} !== null) {
                    $survivor->{$field} = $duplicate->{$field};
                }
            }
            $survivor->save();

            $duplicate->phone = Str::limit("merged:{$duplicate->id}:{$duplicate->phone}", 255, '');
            $duplicate->email = Str::limit("merged:{$duplicate->id}:{$duplicate->email}", 255, '');
            $duplicate->merged_into_id = $survivor->id;
            $duplicate->save();
            $duplicate->delete();

            return $moves;
        });
    }

    /**
     * Every rule that blocks a merge, collected together so the admin sees the
     * full list at once instead of fixing one blocker per attempt.
     *
     * @return list<string>
     */
    private function conflicts(Person $survivor, Person $duplicate): array
    {
        $conflicts = [];

        if ($survivor->user_id !== null && $duplicate->user_id !== null) {
            $conflicts[] = 'Kedua orang memiliki akun login. Nonaktifkan atau hapus salah satu akun terlebih dahulu.';
        }

        $bothMembers = CommunityMembership::whereIn('people_id', [$survivor->id, $duplicate->id])->count() === 2;
        if ($bothMembers) {
            $conflicts[] = 'Kedua orang terdaftar sebagai anggota komunitas.';
        }

        $sharedCohorts = Enrollment::query()
            ->where('people_id', $survivor->id)
            ->whereIn('cohort_id', Enrollment::where('people_id', $duplicate->id)->select('cohort_id'))
            ->with('cohort:id,name')
            ->get();
        foreach ($sharedCohorts as $enrollment) {
            $name = $enrollment->cohort?->name ?? "#{$enrollment->cohort_id}";
            $conflicts[] = "Kedua orang terdaftar di angkatan yang sama: {$name}.";
        }

        return $conflicts;
    }

    /**
     * @return array{applications: int, enrollments: int, membership: bool, account: bool}
     */
    private function moves(Person $duplicate): array
    {
        return [
            'applications' => Application::where('people_id', $duplicate->id)->count(),
            'enrollments' => Enrollment::where('people_id', $duplicate->id)->count(),
            'membership' => CommunityMembership::where('people_id', $duplicate->id)->exists(),
            'account' => $duplicate->user_id !== null,
        ];
    }
}

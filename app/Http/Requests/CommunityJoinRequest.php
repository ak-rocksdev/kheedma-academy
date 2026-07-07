<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Models\Person;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommunityJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Canonicalise the phone before validation so format + matching use +62 form. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::normalize($this->input('phone')),
        ]);

        if (blank($this->input('tiktok_username'))) {
            $this->merge(['tiktok_followers' => null, 'has_started_affiliate' => null, 'affiliate_level' => null, 'affiliate_gmv_range' => null]);
        } elseif (! $this->boolean('has_started_affiliate')) {
            $this->merge(['affiliate_level' => null, 'affiliate_gmv_range' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+62\d{8,13}$/'],
            'email' => [
                'required', 'email:rfc', 'max:160',
                Rule::unique('users', 'email'),
                Rule::unique('people', 'email')
                    ->where(fn ($q) => $q->where('phone', '!=', $this->input('phone')))
                    ->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8'],
            'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['required', Rule::in(Person::GENDERS)],
            'motivation' => ['required', 'string', 'max:1000'],
            'tiktok_username' => ['nullable', 'string', 'max:64'],
            'tiktok_followers' => ['nullable', 'required_with:tiktok_username', 'integer', 'min:0', 'max:1000000000'],
            'has_started_affiliate' => ['nullable', 'required_with:tiktok_username', 'boolean'],
            'affiliate_level' => ['nullable', 'required_if:has_started_affiliate,1', 'integer', 'min:0', 'max:8'],
            'affiliate_gmv_range' => ['nullable', 'required_if:has_started_affiliate,1', Rule::in(Person::GMV_RANGES)],
            'followed_socials' => ['required', 'boolean'],
            'referral_source' => ['required', Rule::in(Application::REFERRAL_SOURCES)],
            // Honeypot: real users never see or fill this; bots do.
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.regex' => 'Format nomor HP tidak valid. Contoh: 0812xxxxxxx.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terpakai. Gunakan email lain atau masuk jika sudah punya akun.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.before' => 'Tanggal lahir tidak valid.',
            'gender.required' => 'Pilih jenis kelaminmu.',
            'gender.in' => 'Pilihan jenis kelamin tidak valid.',
            'motivation.required' => 'Ceritakan alasanmu ingin gabung komunitas.',
            'tiktok_followers.required_with' => 'Isi jumlah followers TikTok-mu.',
            'has_started_affiliate.required_with' => 'Beritahu kami apakah kamu sudah memulai affiliate.',
            'affiliate_level.required_if' => 'Pilih level affiliate-mu.',
            'affiliate_gmv_range.required_if' => 'Pilih rentang GMV-mu.',
            'followed_socials.required' => 'Beritahu kami apakah kamu sudah follow sosial media Kheedma.',
            'referral_source.required' => 'Beritahu kami dari mana kamu tahu komunitas ini.',
            'referral_source.in' => 'Pilihan sumber tidak valid.',
            'website.prohibited' => 'Pengiriman ditolak.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'phone' => 'nomor HP',
            'email' => 'email',
            'password' => 'kata sandi',
            'birth_date' => 'tanggal lahir',
            'gender' => 'jenis kelamin',
            'motivation' => 'motivasi',
            'tiktok_username' => 'akun TikTok',
            'tiktok_followers' => 'jumlah followers TikTok',
            'has_started_affiliate' => 'status memulai affiliate',
            'affiliate_level' => 'level affiliate',
            'affiliate_gmv_range' => 'rentang GMV',
            'followed_socials' => 'follow sosial media',
            'referral_source' => 'sumber informasi',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
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
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+62\d{8,13}$/'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'province_code' => ['required', 'string', 'size:2', 'exists:indonesia_provinces,code'],
            'city_code' => [
                'required', 'string', 'size:4',
                Rule::exists('indonesia_cities', 'code')->where(
                    fn ($q) => $q->where('province_code', $this->input('province_code'))
                ),
            ],
            'tiktok_username' => ['nullable', 'string', 'max:64'],
            'instagram_username' => ['nullable', 'string', 'max:64'],
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
            'province_code.required' => 'Provinsi wajib dipilih.',
            'province_code.exists' => 'Provinsi tidak valid.',
            'city_code.required' => 'Kota/Kabupaten wajib dipilih.',
            'city_code.exists' => 'Kota/Kabupaten tidak valid atau tidak sesuai provinsi.',
            'referral_source.required' => 'Beritahu kami dari mana kamu tahu program ini.',
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
            'province_code' => 'provinsi',
            'city_code' => 'kota/kabupaten',
            'tiktok_username' => 'akun TikTok',
            'instagram_username' => 'akun Instagram',
            'referral_source' => 'sumber informasi',
        ];
    }
}

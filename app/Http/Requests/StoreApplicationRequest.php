<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Models\Person;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
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

        if (blank($this->input('tiktok_username'))) {
            $this->merge(['tiktok_followers' => null, 'has_started_affiliate' => null, 'affiliate_level' => null, 'affiliate_gmv_range' => null]);
        } elseif (! $this->boolean('has_started_affiliate')) {
            $this->merge(['affiliate_level' => null, 'affiliate_gmv_range' => null]);
        }
    }

    public function rules(): array
    {
        $person = Auth::user()?->person;

        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => array_filter([
                'required', 'string', 'regex:/^\+62\d{8,13}$/',
                Auth::check() ? Rule::unique('people', 'phone')->ignore($person?->id)->whereNull('deleted_at') : null,
            ]),
            'email' => [
                'required', 'email:rfc', 'max:160',
                Rule::unique('users', 'email')->ignore(Auth::id()),
                Rule::unique('people', 'email')
                    ->ignore($person?->id)
                    ->where(fn ($q) => $q->where('phone', '!=', $this->input('phone')))
                    ->whereNull('deleted_at'),
            ],
            'password' => Auth::check() ? ['prohibited'] : ['required', 'string', 'min:8'],
            'province_code' => ['required', 'string', 'size:2', 'exists:indonesia_provinces,code'],
            'city_code' => [
                'required', 'string', 'size:4',
                Rule::exists('indonesia_cities', 'code')->where(
                    fn ($q) => $q->where('province_code', $this->input('province_code'))
                ),
            ],
            'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'motivation' => ['required', 'string', 'max:1000'],
            'tiktok_username' => ['nullable', 'string', 'max:64'],
            'tiktok_followers' => ['nullable', 'required_with:tiktok_username', 'integer', 'min:0', 'max:1000000000'],
            'has_started_affiliate' => ['nullable', 'required_with:tiktok_username', 'boolean'],
            'affiliate_level' => ['nullable', 'required_if:has_started_affiliate,1', 'integer', 'min:0', 'max:8'],
            'affiliate_gmv_range' => ['nullable', 'required_if:has_started_affiliate,1', Rule::in(Person::GMV_RANGES)],
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
            'phone.unique' => 'Nomor ini sudah terpakai pendaftar lain.',
            'email.unique' => 'Email ini sudah terpakai. Gunakan email lain atau masuk jika sudah punya akun.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.prohibited' => 'Kamu sudah masuk; kata sandi tidak diperlukan.',
            'province_code.required' => 'Provinsi wajib dipilih.',
            'province_code.exists' => 'Provinsi tidak valid.',
            'city_code.required' => 'Kota/Kabupaten wajib dipilih.',
            'city_code.exists' => 'Kota/Kabupaten tidak valid atau tidak sesuai provinsi.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.before' => 'Tanggal lahir tidak valid.',
            'motivation.required' => 'Ceritakan kenapa kamu ingin ikut program ini.',
            'tiktok_followers.required_with' => 'Isi jumlah followers TikTok-mu.',
            'has_started_affiliate.required_with' => 'Beritahu kami apakah kamu sudah memulai affiliate.',
            'affiliate_level.required_if' => 'Pilih level affiliate-mu.',
            'affiliate_gmv_range.required_if' => 'Pilih rentang GMV-mu.',
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
            'password' => 'kata sandi',
            'province_code' => 'provinsi',
            'city_code' => 'kota/kabupaten',
            'birth_date' => 'tanggal lahir',
            'motivation' => 'motivasi',
            'tiktok_username' => 'akun TikTok',
            'tiktok_followers' => 'jumlah followers TikTok',
            'has_started_affiliate' => 'status memulai affiliate',
            'affiliate_level' => 'level affiliate',
            'affiliate_gmv_range' => 'rentang GMV',
            'instagram_username' => 'akun Instagram',
            'referral_source' => 'sumber informasi',
        ];
    }
}

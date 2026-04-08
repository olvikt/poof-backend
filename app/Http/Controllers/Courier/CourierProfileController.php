<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Actions\Courier\Profile\PersistCourierAvatarAction;
use App\Actions\Courier\Profile\PersistCourierProfileAction;
use App\Actions\Courier\Verification\SubmitCourierVerificationRequestAction;
use App\DTO\Avatar\AvatarUploadData;
use App\DTO\Courier\Profile\CourierProfileUpdateData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Courier\Profile\CourierProfileReadModelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CourierProfileController extends Controller
{
    public function show(CourierProfileReadModelService $readModelService)
    {
        $courier = $this->resolveCourier();

        abort_if(! $courier instanceof User, 403);

        $profile = $readModelService->forCourier($courier);
        $parsedResidenceAddress = $this->parseResidenceAddress($courier->residence_address);

        Log::info('courier_profile_render', [
            'flow' => 'courier_profile',
            'courier_id' => $courier->id,
            'has_withdrawal_access' => $profile['balance_summary']['can_request_withdrawal'],
        ]);

        return view('courier.profile', [
            'profile' => $profile,
            'courier' => $courier,
            'cityOptions' => $this->cityOptions(),
            'residenceCity' => $parsedResidenceAddress['city'],
            'residenceAddressLine' => $parsedResidenceAddress['line'],
        ]);
    }

    public function update(Request $request, PersistCourierProfileAction $action): RedirectResponse
    {
        $courier = $this->resolveCourier();
        abort_if(! $courier instanceof User, 403);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$courier->id],
            'residence_city' => ['required', 'string', 'in:Київ,Львів,Одеса,Харків,Дніпро'],
            'residence_address_line' => ['required', 'string', 'max:500'],
        ]);

        $residenceAddress = $this->composeResidenceAddress(
            (string) $payload['residence_city'],
            (string) $payload['residence_address_line'],
        );

        if (mb_strlen($residenceAddress) > 500) {
            throw ValidationException::withMessages([
                'residence_address_line' => 'Адреса завелика. Максимум 500 символів разом із містом.',
            ]);
        }

        $action->execute($courier, new CourierProfileUpdateData(
            name: (string) $payload['name'],
            phone: (string) $payload['phone'],
            email: (string) $payload['email'],
            residenceAddress: $residenceAddress,
        ));

        Log::info('courier_profile_update', [
            'flow' => 'courier_profile',
            'courier_id' => $courier->id,
        ]);

        return back()->with('success', 'Профіль курʼєра оновлено.');
    }

    public function updateAvatar(Request $request, PersistCourierAvatarAction $action): RedirectResponse
    {
        $courier = $this->resolveCourier();
        abort_if(! $courier instanceof User, 403);

        $payload = $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $action->execute($courier, new AvatarUploadData($payload['avatar']));

        return back()->with('success', 'Аватар курʼєра оновлено.');
    }


    public function submitVerification(Request $request, SubmitCourierVerificationRequestAction $action): RedirectResponse
    {
        $courier = $this->resolveCourier();
        abort_if(! $courier instanceof User, 403);

        $maxKilobytes = max(1, (int) ceil(((int) config('courier_verification.max_file_size_bytes', 5 * 1024 * 1024)) / 1024));
        $allowedMimeTypes = (array) config('courier_verification.allowed_mime_types', []);

        $payload = $request->validate([
            'document_type' => ['required', 'string', 'in:passport,id_card'],
            'document' => ['required', 'file', 'mimetypes:'.implode(',', $allowedMimeTypes), 'max:'.$maxKilobytes],
        ]);

        $action->execute(
            $courier,
            (string) $payload['document_type'],
            $payload['document'],
        );

        Log::info('courier_verification_submitted', [
            'flow' => 'courier_verification',
            'courier_id' => $courier->id,
            'document_type' => $payload['document_type'],
        ]);

        return back()->with('success', 'Документ відправлено на перевірку.');
    }

    private function resolveCourier(): ?User
    {
        $user = auth()->user();

        return $user instanceof User && $user->isCourier()
            ? $user
            : null;
    }

    private function cityOptions(): array
    {
        return ['Київ', 'Львів', 'Одеса', 'Харків', 'Дніпро'];
    }

    private function parseResidenceAddress(?string $residenceAddress): array
    {
        $normalized = trim((string) $residenceAddress);

        if ($normalized === '') {
            return ['city' => 'Київ', 'line' => ''];
        }

        $citiesPattern = implode('|', array_map(static fn (string $city): string => preg_quote($city, '/'), $this->cityOptions()));
        $pattern = '/^(?:м\.\s*|м\s+)?('.$citiesPattern.')\s*,\s*(.+)$/u';

        if (preg_match($pattern, $normalized, $matches) === 1) {
            return [
                'city' => $matches[1],
                'line' => trim($matches[2]),
            ];
        }

        foreach ($this->cityOptions() as $city) {
            $prefix = $city.',';
            if (str_starts_with($normalized, $prefix)) {
                return [
                    'city' => $city,
                    'line' => ltrim(substr($normalized, strlen($prefix))),
                ];
            }
        }

        return ['city' => 'Київ', 'line' => $normalized];
    }

    private function composeResidenceAddress(string $city, string $addressLine): string
    {
        $line = trim($addressLine);
        $legacyPattern = '/^(?:м\.\s*|м\s+)?'.preg_quote($city, '/').'\s*,\s*/u';
        $line = preg_replace($legacyPattern, '', $line) ?? $line;

        return trim($city.', '.$line);
    }
}

<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VinDecoderService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.vin_decoder.base_url')) !== '';
    }

    /**
     * @return array{
     *     vin:string,
     *     success:bool,
     *     message:string,
     *     make:string,
     *     model:string,
     *     model_year:string,
     *     trim:string,
     *     body_class:string,
     *     engine:string,
     *     fuel_type:string,
     *     transmission:string,
     *     drivetrain:string,
     *     manufacturer:string,
     *     provider_used:string,
     *     raw:array<string,mixed>|null
     * }
     */
    public function decode(string $vin): array
    {
        $vin = $this->normalizeVin($vin);
        $result = $this->emptyResult($vin);
        if ($vin === '') {
            $result['message'] = 'VIN пустой или содержит недопустимые символы.';

            return $result;
        }

        // 1) Сначала Vincario: если получили полный набор данных, дальше не запрашиваем.
        $vincarioPayload = $this->requestVincario($vin);
        if (is_array($vincarioPayload)) {
            $result = $this->mergeFromVincarioPayload($result, $vincarioPayload);
            $result['provider_used'] = $result['provider_used'] !== '' ? $result['provider_used'] : 'vincario_decode';
            $raw = is_array($result['raw']) ? $result['raw'] : [];
            $result['raw'] = array_merge($raw, ['vincario' => $vincarioPayload]);
            if ($this->hasDisplayDataComplete($result)) {
                $result['success'] = true;
                $result['message'] = 'OK';

                return $result;
            }
        }

        // 2) NHTSA DecodeVinValues: дополняем только пустые поля.
        $nhtsaValuesPayload = $this->requestDecode($vin);
        if (is_array($nhtsaValuesPayload)) {
            $rows = $nhtsaValuesPayload['Results'] ?? null;
            if (is_array($rows) && $rows !== [] && is_array($rows[0])) {
                /** @var array<string,mixed> $row */
                $row = $rows[0];
                $errorCode = trim((string) ($row['ErrorCode'] ?? ''));
                $errorText = trim((string) ($row['ErrorText'] ?? ''));
                $result = $this->mergeFromNhtsaValuesRow($result, $row, $errorCode, $errorText);
                if ($result['provider_used'] === '' && ! $this->hasCriticalGaps($result)) {
                    $result['provider_used'] = 'nhtsa_decode_vin_values';
                }
            }
            $raw = is_array($result['raw']) ? $result['raw'] : [];
            $result['raw'] = array_merge($raw, ['decode_vin_values' => $nhtsaValuesPayload]);
        }

        // 3) NHTSA DecodeVin variables: дополняем только пустые поля.
        if ($this->hasCriticalGaps($result)) {
            $fallbackPayload = $this->requestDecodeByVariables($vin);
            if (is_array($fallbackPayload)) {
                $result = $this->mergeFromVariablePayload($result, $fallbackPayload);
                if ($result['provider_used'] === '' && ! $this->hasCriticalGaps($result)) {
                    $result['provider_used'] = 'nhtsa_decode_vin';
                }
                $raw = is_array($result['raw']) ? $result['raw'] : [];
                $result['raw'] = array_merge($raw, ['decode_vin' => $fallbackPayload]);
            }
        }

        // 4) RapidAPI VIN: дополняем только пустые поля.
        if ($this->hasCriticalGaps($result)) {
            $rapidApiPayload = $this->requestRapidApiVin($vin);
            if (is_array($rapidApiPayload)) {
                $result = $this->mergeFromRapidApiPayload($result, $rapidApiPayload);
                if ($result['provider_used'] === '' && ! $this->hasCriticalGaps($result)) {
                    $result['provider_used'] = 'rapidapi_vin';
                }
                $existingRaw = is_array($result['raw']) ? $result['raw'] : [];
                $result['raw'] = array_merge($existingRaw, [
                    'rapidapi_vin' => $rapidApiPayload,
                ]);
            }
        }

        // 5) Вторичный провайдер: только дозаполнение пустых полей.
        if ($this->hasCriticalGaps($result)) {
            $secondaryPayload = $this->requestSecondaryProvider($vin);
            if (is_array($secondaryPayload)) {
                $result = $this->mergeFromSecondaryPayload($result, $secondaryPayload);
                if ($result['provider_used'] === '' && ! $this->hasCriticalGaps($result)) {
                    $result['provider_used'] = 'api_ninjas_vinlookup';
                }
                $existingRaw = is_array($result['raw']) ? $result['raw'] : [];
                $result['raw'] = array_merge($existingRaw, [
                    'secondary_provider' => $secondaryPayload,
                ]);
            }
        }

        if ($result['message'] === '') {
            $result['message'] = $this->hasCriticalGaps($result)
                ? 'Не удалось полноценно декодировать VIN. Попробуйте проверить корректность VIN.'
                : 'OK (часть полей дозаполнена несколькими источниками)';
        }
        if (! $result['success'] && ! $this->hasCriticalGaps($result)) {
            $result['success'] = true;
        }

        return $result;
    }

    protected function normalizeVin(string $vin): string
    {
        $vin = mb_strtoupper(trim($vin));

        // VIN uses only letters/digits; I/O/Q are skipped in standard VIN alphabet.
        return preg_replace('/[^A-Z0-9]/', '', $vin) ?? '';
    }

    protected function composeEngine(array $row): string
    {
        $parts = array_filter([
            $this->str($row['DisplacementL'] ?? null) !== '' ? $this->str($row['DisplacementL'] ?? null).'L' : '',
            $this->str($row['EngineCylinders'] ?? null) !== '' ? $this->str($row['EngineCylinders'] ?? null).' cyl' : '',
            $this->str($row['EngineModel'] ?? null),
        ], static fn (string $v): bool => $v !== '');

        return implode(', ', $parts);
    }

    protected function str(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array(mb_strtolower($value), ['', 'null', 'not applicable'], true) ? '' : $value;
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function requestDecode(string $vin): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.vin_decoder.base_url'), '/');
        $url = $baseUrl.'/vehicles/DecodeVinValues/'.rawurlencode($vin).'?format=json';
        $timeout = (int) config('services.vin_decoder.timeout', 20);

        try {
            /** @var Response $response */
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('VinDecoderService: HTTP error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->ok()) {
            Log::info('VinDecoderService: non-200', [
                'url' => $url,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * Fallback endpoint: /DecodeVin возвращает массив Variable/Value.
     *
     * @return array<string,mixed>|null
     */
    protected function requestDecodeByVariables(string $vin): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.vin_decoder.base_url'), '/');
        $url = $baseUrl.'/vehicles/DecodeVin/'.rawurlencode($vin).'?format=json';
        $timeout = (int) config('services.vin_decoder.timeout', 20);

        try {
            /** @var Response $response */
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('VinDecoderService: fallback HTTP error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->ok()) {
            Log::info('VinDecoderService: fallback non-200', [
                'url' => $url,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * @param  array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}  $result
     * @param  array<string,mixed>  $payload
     * @return array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}
     */
    protected function mergeFromVariablePayload(array $result, array $payload): array
    {
        $map = $this->extractVariableMap($payload);

        $result['make'] = $result['make'] !== '' ? $result['make'] : $this->pickFirst($map, ['Make']);
        $result['model'] = $result['model'] !== '' ? $result['model'] : $this->pickFirst($map, ['Model']);
        $result['model_year'] = $result['model_year'] !== '' ? $result['model_year'] : $this->pickFirst($map, ['Model Year']);
        $result['trim'] = $result['trim'] !== '' ? $result['trim'] : $this->pickFirst($map, ['Trim']);
        $result['body_class'] = $result['body_class'] !== '' ? $result['body_class'] : $this->pickFirst($map, ['Body Class']);
        $result['fuel_type'] = $result['fuel_type'] !== '' ? $result['fuel_type'] : $this->pickFirst($map, ['Fuel Type - Primary', 'Fuel Type Primary']);
        $result['transmission'] = $result['transmission'] !== '' ? $result['transmission'] : $this->pickFirst($map, ['Transmission Style']);
        $result['drivetrain'] = $result['drivetrain'] !== '' ? $result['drivetrain'] : $this->pickFirst($map, ['Drive Type']);
        $result['manufacturer'] = $result['manufacturer'] !== '' ? $result['manufacturer'] : $this->pickFirst($map, ['Manufacturer Name']);

        if ($result['engine'] === '') {
            $engineParts = array_filter([
                $this->pickFirst($map, ['Displacement (L)']) !== '' ? $this->pickFirst($map, ['Displacement (L)']).'L' : '',
                $this->pickFirst($map, ['Engine Number of Cylinders']) !== '' ? $this->pickFirst($map, ['Engine Number of Cylinders']).' cyl' : '',
                $this->pickFirst($map, ['Engine Model']),
            ], static fn (string $s): bool => $s !== '');
            $result['engine'] = implode(', ', $engineParts);
        }

        return $result;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,string>
     */
    protected function extractVariableMap(array $payload): array
    {
        $rows = $payload['Results'] ?? null;
        if (! is_array($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['Variable'] ?? ''));
            $value = $this->str($row['Value'] ?? null);
            if ($name !== '' && $value !== '') {
                $map[$name] = $value;
            }
        }

        return $map;
    }

    /**
     * @param  array<string,string>  $map
     * @param  list<string>  $keys
     */
    protected function pickFirst(array $map, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->str($map[$key] ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}  $result
     */
    protected function hasCriticalGaps(array $result): bool
    {
        return $result['make'] === '' || $result['model'] === '';
    }

    /**
     * Критерий «полные данные для UI» — если всё это есть, можно останавливать цепочку.
     *
     * @param  array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}  $result
     */
    protected function hasDisplayDataComplete(array $result): bool
    {
        return $result['make'] !== ''
            && $result['model'] !== ''
            && $result['model_year'] !== ''
            && $result['body_class'] !== ''
            && $result['engine'] !== ''
            && $result['fuel_type'] !== ''
            && $result['transmission'] !== '';
    }

    /**
     * @param  array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}  $result
     * @param  array<string,mixed>  $row
     * @return array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}
     */
    protected function mergeFromNhtsaValuesRow(array $result, array $row, string $errorCode, string $errorText): array
    {
        $result['make'] = $result['make'] !== '' ? $result['make'] : $this->str($row['Make'] ?? null);
        $result['model'] = $result['model'] !== '' ? $result['model'] : $this->str($row['Model'] ?? null);
        $result['model_year'] = $result['model_year'] !== '' ? $result['model_year'] : $this->str($row['ModelYear'] ?? null);
        $result['trim'] = $result['trim'] !== '' ? $result['trim'] : $this->str($row['Trim'] ?? null);
        $result['body_class'] = $result['body_class'] !== '' ? $result['body_class'] : $this->str($row['BodyClass'] ?? null);
        $result['fuel_type'] = $result['fuel_type'] !== '' ? $result['fuel_type'] : $this->str($row['FuelTypePrimary'] ?? null);
        $result['transmission'] = $result['transmission'] !== '' ? $result['transmission'] : $this->str($row['TransmissionStyle'] ?? null);
        $result['drivetrain'] = $result['drivetrain'] !== '' ? $result['drivetrain'] : $this->str($row['DriveType'] ?? null);
        $result['manufacturer'] = $result['manufacturer'] !== '' ? $result['manufacturer'] : $this->str($row['Manufacturer'] ?? null);
        if ($result['engine'] === '') {
            $result['engine'] = $this->composeEngine($row);
        }
        if ($result['message'] === '') {
            $result['message'] = $errorCode === '0'
                ? 'OK'
                : ($errorText !== '' ? $errorText : 'VIN разобран с ошибками/ограничениями.');
        }
        if ($errorCode === '0' && ! $this->hasCriticalGaps($result)) {
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function requestSecondaryProvider(string $vin): ?array
    {
        $provider = trim((string) config('services.vin_decoder.secondary_provider', ''));
        if ($provider !== 'api_ninjas') {
            return null;
        }

        $key = trim((string) config('services.vin_decoder.secondary_key', ''));
        $baseUrl = rtrim((string) config('services.vin_decoder.secondary_base_url', ''), '/');
        if ($key === '' || $baseUrl === '') {
            return null;
        }

        $url = $baseUrl.'/vinlookup?vin='.rawurlencode($vin);
        $timeout = (int) config('services.vin_decoder.secondary_timeout', 20);

        try {
            /** @var Response $response */
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders(['X-Api-Key' => $key])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('VinDecoderService: secondary HTTP error', [
                'provider' => $provider,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->ok()) {
            Log::info('VinDecoderService: secondary non-200', [
                'provider' => $provider,
                'url' => $url,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function requestVincario(string $vin): ?array
    {
        $apiKey = trim((string) config('services.vin_decoder.vincario_api_key', ''));
        $secret = trim((string) config('services.vin_decoder.vincario_secret_key', ''));
        $baseUrl = rtrim((string) config('services.vin_decoder.vincario_base_url', ''), '/');
        if ($apiKey === '' || $secret === '' || $baseUrl === '') {
            return null;
        }

        $id = 'decode';
        $controlSum = substr(sha1($vin.'|'.$id.'|'.$apiKey.'|'.$secret), 0, 10);
        $url = $baseUrl.'/'.$apiKey.'/'.$controlSum.'/decode/'.rawurlencode($vin).'.json';
        $timeout = (int) config('services.vin_decoder.vincario_timeout', 20);

        try {
            /** @var Response $response */
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('VinDecoderService: vincario HTTP error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->ok()) {
            Log::info('VinDecoderService: vincario non-200', [
                'url' => $url,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function requestRapidApiVin(string $vin): ?array
    {
        $key = trim((string) config('services.vin_decoder.rapidapi_key', ''));
        $host = trim((string) config('services.vin_decoder.rapidapi_host', ''));
        $baseUrl = rtrim((string) config('services.vin_decoder.rapidapi_base_url', ''), '/');
        $path = trim((string) config('services.vin_decoder.rapidapi_path', '/decode'));
        if ($key === '' || $host === '' || $baseUrl === '') {
            return null;
        }

        $url = $baseUrl.'/'.ltrim($path, '/').'?vin='.rawurlencode($vin);
        $timeout = (int) config('services.vin_decoder.rapidapi_timeout', 20);

        try {
            /** @var Response $response */
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders([
                    'X-RapidAPI-Key' => $key,
                    'X-RapidAPI-Host' => $host,
                ])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('VinDecoderService: rapidapi HTTP error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->ok()) {
            Log::info('VinDecoderService: rapidapi non-200', [
                'url' => $url,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * @param  array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}  $result
     * @param  array<string,mixed>  $payload
     * @return array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}
     */
    protected function mergeFromVincarioPayload(array $result, array $payload): array
    {
        $source = [];
        if (is_array($payload['decode'] ?? null)) {
            $decoded = $payload['decode'];
            if ($decoded !== [] && is_array($decoded[0] ?? null) && array_key_exists('label', $decoded[0])) {
                foreach ($decoded as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $label = mb_strtolower(trim((string) ($item['label'] ?? '')));
                    $value = $item['value'] ?? null;
                    if ($label !== '' && $value !== null && $value !== '') {
                        $source[$label] = $value;
                    }
                }
            } else {
                $source = $decoded;
            }
        } elseif (is_array($payload['result'] ?? null)) {
            $source = $payload['result'];
        } else {
            $source = $payload;
        }

        $result['make'] = $result['make'] !== '' ? $result['make'] : $this->pickAny($source, ['make', 'manufacturer', 'manufacturer_name']);
        $result['model'] = $result['model'] !== '' ? $result['model'] : $this->pickAny($source, ['model', 'model_name']);
        $result['model_year'] = $result['model_year'] !== '' ? $result['model_year'] : $this->pickAny($source, ['model year', 'model_year', 'year']);
        $result['trim'] = $result['trim'] !== '' ? $result['trim'] : $this->pickAny($source, ['trim', 'series']);
        $result['body_class'] = $result['body_class'] !== '' ? $result['body_class'] : $this->pickAny($source, ['body', 'body_type', 'body_class', 'vehicle_type']);
        $result['fuel_type'] = $result['fuel_type'] !== '' ? $result['fuel_type'] : $this->pickAny($source, ['fuel type - primary', 'fuel_type', 'fuel']);
        $result['transmission'] = $result['transmission'] !== '' ? $result['transmission'] : $this->pickAny($source, ['transmission', 'transmission_style']);
        $result['drivetrain'] = $result['drivetrain'] !== '' ? $result['drivetrain'] : $this->pickAny($source, ['drive', 'drivetrain', 'drive_type']);
        $result['manufacturer'] = $result['manufacturer'] !== '' ? $result['manufacturer'] : $this->pickAny($source, ['manufacturer', 'manufacturer_name', 'make']);

        if ($result['engine'] === '') {
            $engineParts = array_filter([
                $this->pickAny($source, ['engine displacement (ccm)', 'engine_displacement_l', 'engine_size', 'displacement_l']) !== ''
                    ? $this->pickAny($source, ['engine displacement (ccm)', 'engine_displacement_l', 'engine_size', 'displacement_l']).' ccm'
                    : '',
                $this->pickAny($source, ['engine_cylinders', 'cylinders']) !== ''
                    ? $this->pickAny($source, ['engine_cylinders', 'cylinders']).' cyl'
                    : '',
                $this->pickAny($source, ['engine code', 'engine_model', 'engine']),
            ], static fn (string $v): bool => $v !== '');

            $result['engine'] = implode(', ', $engineParts);
        }

        if ($result['message'] !== 'OK' && ! $this->hasCriticalGaps($result)) {
            $result['message'] = 'OK (часть полей дозаполнена Vincario)';
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * @param  array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}  $result
     * @param  array<string,mixed>  $payload
     * @return array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}
     */
    protected function mergeFromRapidApiPayload(array $result, array $payload): array
    {
        $source = $this->normalizeRapidApiShape($payload);

        $result['make'] = $result['make'] !== '' ? $result['make'] : $this->pickAny($source, ['make', 'brand', 'manufacturer', 'manufacturer_name']);
        $result['model'] = $result['model'] !== '' ? $result['model'] : $this->pickAny($source, ['model', 'model_name']);
        $result['model_year'] = $result['model_year'] !== '' ? $result['model_year'] : $this->pickAny($source, ['year', 'model_year']);
        $result['trim'] = $result['trim'] !== '' ? $result['trim'] : $this->pickAny($source, ['trim', 'series']);
        $result['body_class'] = $result['body_class'] !== '' ? $result['body_class'] : $this->pickAny($source, ['body_class', 'body_type', 'vehicle_type']);
        $result['fuel_type'] = $result['fuel_type'] !== '' ? $result['fuel_type'] : $this->pickAny($source, ['fuel_type', 'fuel']);
        $result['transmission'] = $result['transmission'] !== '' ? $result['transmission'] : $this->pickAny($source, ['transmission', 'transmission_style']);
        $result['drivetrain'] = $result['drivetrain'] !== '' ? $result['drivetrain'] : $this->pickAny($source, ['drive', 'drive_type', 'drivetrain']);
        $result['manufacturer'] = $result['manufacturer'] !== '' ? $result['manufacturer'] : $this->pickAny($source, ['manufacturer', 'manufacturer_name', 'make']);

        if ($result['engine'] === '') {
            $engineParts = array_filter([
                $this->pickAny($source, ['engine_size', 'displacement_l']) !== '' ? $this->pickAny($source, ['engine_size', 'displacement_l']).'L' : '',
                $this->pickAny($source, ['cylinders', 'engine_cylinders']) !== '' ? $this->pickAny($source, ['cylinders', 'engine_cylinders']).' cyl' : '',
                $this->pickAny($source, ['engine', 'engine_model']),
            ], static fn (string $v): bool => $v !== '');

            $result['engine'] = implode(', ', $engineParts);
        }

        if ($result['message'] !== 'OK' && ! $this->hasCriticalGaps($result)) {
            $result['message'] = 'OK (часть полей дозаполнена RapidAPI VIN)';
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    protected function normalizeRapidApiShape(array $payload): array
    {
        if (isset($payload[0]) && is_array($payload[0])) {
            return $payload[0];
        }
        if (isset($payload['data']) && is_array($payload['data'])) {
            if (isset($payload['data'][0]) && is_array($payload['data'][0])) {
                return $payload['data'][0];
            }

            return $payload['data'];
        }
        if (isset($payload['vehicle']) && is_array($payload['vehicle'])) {
            return $payload['vehicle'];
        }

        return $payload;
    }

    /**
     * @param  array<string,mixed>  $source
     * @param  list<string>  $keys
     */
    protected function pickAny(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = mb_strtolower(trim($key));
            $value = $this->str($source[$key] ?? ($source[$normalized] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}  $result
     * @param  array<string,mixed>  $payload
     * @return array{vin:string,success:bool,message:string,make:string,model:string,model_year:string,trim:string,body_class:string,engine:string,fuel_type:string,transmission:string,drivetrain:string,manufacturer:string,provider_used:string,raw:array<string,mixed>|null}
     */
    protected function mergeFromSecondaryPayload(array $result, array $payload): array
    {
        $result['make'] = $result['make'] !== '' ? $result['make'] : $this->str($payload['make'] ?? '');
        $result['model'] = $result['model'] !== '' ? $result['model'] : $this->str($payload['model'] ?? '');
        $result['model_year'] = $result['model_year'] !== '' ? $result['model_year'] : $this->str($payload['year'] ?? '');
        $result['trim'] = $result['trim'] !== '' ? $result['trim'] : $this->str($payload['trim'] ?? '');
        $result['body_class'] = $result['body_class'] !== '' ? $result['body_class'] : $this->str($payload['vehicle_type'] ?? '');
        $result['fuel_type'] = $result['fuel_type'] !== '' ? $result['fuel_type'] : $this->str($payload['fuel_type'] ?? '');
        $result['transmission'] = $result['transmission'] !== '' ? $result['transmission'] : $this->str($payload['transmission'] ?? '');
        $result['drivetrain'] = $result['drivetrain'] !== '' ? $result['drivetrain'] : $this->str($payload['drive'] ?? '');
        $result['manufacturer'] = $result['manufacturer'] !== '' ? $result['manufacturer'] : $this->str($payload['manufacturer'] ?? '');

        if ($result['engine'] === '') {
            $engineParts = array_filter([
                $this->str($payload['engine_size'] ?? '') !== '' ? $this->str($payload['engine_size'] ?? '').'L' : '',
                $this->str($payload['cylinders'] ?? '') !== '' ? $this->str($payload['cylinders'] ?? '').' cyl' : '',
                $this->str($payload['engine'] ?? ''),
            ], static fn (string $v): bool => $v !== '');

            $result['engine'] = implode(', ', $engineParts);
        }

        if ($result['message'] !== 'OK' && ! $this->hasCriticalGaps($result)) {
            $result['message'] = 'OK (часть полей дозаполнена вторым провайдером)';
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * @return array{
     *     vin:string,
     *     success:bool,
     *     message:string,
     *     make:string,
     *     model:string,
     *     model_year:string,
     *     trim:string,
     *     body_class:string,
     *     engine:string,
     *     fuel_type:string,
     *     transmission:string,
     *     drivetrain:string,
     *     manufacturer:string,
     *     provider_used:string,
     *     raw:array<string,mixed>|null
     * }
     */
    protected function emptyResult(string $vin): array
    {
        return [
            'vin' => $vin,
            'success' => false,
            'message' => '',
            'make' => '',
            'model' => '',
            'model_year' => '',
            'trim' => '',
            'body_class' => '',
            'engine' => '',
            'fuel_type' => '',
            'transmission' => '',
            'drivetrain' => '',
            'manufacturer' => '',
            'provider_used' => '',
            'raw' => null,
        ];
    }
}


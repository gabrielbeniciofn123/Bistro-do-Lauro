<?php
declare(strict_types=1);

final class SettingsService
{
    public static function get(): array
    {
        $settings = Database::connection()->query('SELECT * FROM restaurant_settings WHERE id = 1')->fetch();
        if (!$settings) {
            throw new RuntimeException('Configurações do restaurante não encontradas.');
        }
        $settings['service_fee_enabled'] = (bool) $settings['service_fee_enabled'];
        $settings['restaurant_open'] = (bool) $settings['restaurant_open'];
        return $settings;
    }

    public static function update(array $data): array
    {
        $name = trim((string) ($data['restaurant_name'] ?? ''));
        $timezone = trim((string) ($data['timezone'] ?? 'America/Sao_Paulo'));
        if ($name === '' || mb_strlen($name) > 150) {
            throw new DomainException('Informe o nome do restaurante.');
        }
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new DomainException('Fuso horário inválido.');
        }
        $serviceFee = decimal_value($data['service_fee_percent'] ?? '10.00');
        if ((float) $serviceFee > 100) {
            throw new DomainException('A taxa de serviço não pode ultrapassar 100%.');
        }

        $statement = Database::connection()->prepare(
            'UPDATE restaurant_settings SET
                restaurant_name = :restaurant_name, phone = :phone, whatsapp = :whatsapp,
                address = :address, cnpj = :cnpj, currency = :currency, timezone = :timezone,
                service_fee_enabled = :service_fee_enabled, service_fee_percent = :service_fee_percent,
                restaurant_open = :restaurant_open
             WHERE id = 1'
        );
        $statement->execute([
            'restaurant_name' => $name,
            'phone' => self::nullable($data['phone'] ?? null, 30),
            'whatsapp' => self::nullable($data['whatsapp'] ?? null, 30),
            'address' => self::nullable($data['address'] ?? null, 255),
            'cnpj' => self::nullable($data['cnpj'] ?? null, 20),
            'currency' => preg_match('/^[A-Z]{3}$/', (string) ($data['currency'] ?? 'BRL')) ? $data['currency'] : 'BRL',
            'timezone' => $timezone,
            'service_fee_enabled' => !empty($data['service_fee_enabled']) ? 1 : 0,
            'service_fee_percent' => $serviceFee,
            'restaurant_open' => !empty($data['restaurant_open']) ? 1 : 0,
        ]);
        audit_log('settings_updated', 'restaurant_settings', 1);
        return self::get();
    }

    private static function nullable(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        return mb_substr($value, 0, $max);
    }
}

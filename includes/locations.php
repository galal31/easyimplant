<?php

function getEgyptGovernorates(): array
{
    return [
        'cairo' => 'Cairo',
        'giza' => 'Giza',
        'alexandria' => 'Alexandria',
        'dakahlia' => 'Dakahlia',
        'red-sea' => 'Red Sea',
        'beheira' => 'Beheira',
        'fayoum' => 'Fayoum',
        'gharbia' => 'Gharbia',
        'ismailia' => 'Ismailia',
        'menofia' => 'Menofia',
        'minya' => 'Minya',
        'qalyubia' => 'Qalyubia',
        'new-valley' => 'New Valley',
        'suez' => 'Suez',
        'aswan' => 'Aswan',
        'assiut' => 'Assiut',
        'beni-suef' => 'Beni Suef',
        'port-said' => 'Port Said',
        'damietta' => 'Damietta',
        'sharqia' => 'Sharqia',
        'south-sinai' => 'South Sinai',
        'kafr-el-sheikh' => 'Kafr El Sheikh',
        'matrouh' => 'Matrouh',
        'luxor' => 'Luxor',
        'qena' => 'Qena',
        'north-sinai' => 'North Sinai',
        'sohag' => 'Sohag',
    ];
}

function getOutsideEgyptCountries(): array
{
    return [
        'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda',
        'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas',
        'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin',
        'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei',
        'Bulgaria', 'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cambodia', 'Cameroon',
        'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia',
        'Comoros', 'Congo', 'Costa Rica', 'Croatia', 'Cuba', 'Cyprus', 'Czechia',
        'Democratic Republic of the Congo', 'Denmark', 'Djibouti', 'Dominica',
        'Dominican Republic', 'Ecuador', 'El Salvador', 'Equatorial Guinea', 'Eritrea',
        'Estonia', 'Eswatini', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon',
        'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala',
        'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 'Honduras', 'Hungary', 'Iceland',
        'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Ivory Coast',
        'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kosovo',
        'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia',
        'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Madagascar', 'Malawi',
        'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Mauritania',
        'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia',
        'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia', 'Nauru', 'Nepal',
        'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea',
        'North Macedonia', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine', 'Panama',
        'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal',
        'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saint Kitts and Nevis', 'Saint Lucia',
        'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia',
        'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia',
        'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa', 'South Korea',
        'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden',
        'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand',
        'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Türkiye',
        'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine', 'United Arab Emirates',
        'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan', 'Vanuatu',
        'Vatican City', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe',
    ];
}

function getGovernorateLabel(?string $code): string
{
    $governorates = getEgyptGovernorates();
    return $governorates[$code ?? ''] ?? ($code ?: 'Not specified');
}

function formatClinicLocation(?string $country, ?string $governorate): string
{
    if (strtolower((string) $country) === 'egypt') {
        return 'Egypt / ' . getGovernorateLabel($governorate);
    }

    $legacyLabels = [
        'ksa' => 'Saudi Arabia',
        'gulf' => 'Other Gulf Country',
    ];
    return $legacyLabels[strtolower((string) $country)] ?? ($country ?: 'Not specified');
}

function ensureSurgeonGovernoratePricingSchema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS surgeon_governorate_prices (
            governorate_code VARCHAR(50) NOT NULL PRIMARY KEY,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            is_available TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        ALTER TABLE surgeon_governorate_prices
          ADD COLUMN IF NOT EXISTS is_available TINYINT(1) NOT NULL DEFAULT 1 AFTER price
    ");

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO surgeon_governorate_prices (governorate_code, price)
        VALUES (:governorate_code, 0.00)
    ");
    foreach (array_keys(getEgyptGovernorates()) as $code) {
        $stmt->execute([':governorate_code' => $code]);
    }

    $ensured = true;
}

function getSurgeonGovernoratePrices(PDO $pdo): array
{
    ensureSurgeonGovernoratePricingSchema($pdo);
    $rows = $pdo->query("SELECT governorate_code, price FROM surgeon_governorate_prices")->fetchAll(PDO::FETCH_KEY_PAIR);
    $result = [];
    foreach (getEgyptGovernorates() as $code => $label) {
        $result[$code] = (float) ($rows[$code] ?? 0);
    }
    return $result;
}

function getSurgeonGovernorateSettings(PDO $pdo): array
{
    ensureSurgeonGovernoratePricingSchema($pdo);
    $rows = $pdo->query("
        SELECT governorate_code, price, is_available
        FROM surgeon_governorate_prices
    ")->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    $result = [];
    foreach (getEgyptGovernorates() as $code => $label) {
        $result[$code] = [
            'label' => $label,
            'price' => (float) ($rows[$code]['price'] ?? 0),
            'is_available' => (int) ($rows[$code]['is_available'] ?? 1) === 1,
        ];
    }
    return $result;
}

function getAvailableEgyptGovernorates(PDO $pdo): array
{
    $available = [];
    foreach (getSurgeonGovernorateSettings($pdo) as $code => $setting) {
        if ($setting['is_available']) {
            $available[$code] = $setting['label'];
        }
    }
    return $available;
}
